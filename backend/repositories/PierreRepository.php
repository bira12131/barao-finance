<?php
/**
 * REPOSITORY — PierreRepository
 *
 * CRUD para as tabelas pierre_* no PostgreSQL.
 * Usado pelo PierreService para persistir e recuperar dados da API Pierre Finance.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/schema.php';
require_once __DIR__ . '/../utils/marcas.php';
require_once __DIR__ . '/../utils/classificacao.php';

class PierreRepository
{
    /**
     * Do mais recente para o mais antigo, pela data e hora reais da transação (campo date da API).
     * Só o dia (coluna data) não basta: dentro do mesmo dia a ordem ficava aleatória.
     */
    private const ORDEM_RECENTE = "
        CASE WHEN dados_raw->>'date' ~ '^\\d{4}-\\d{2}-\\d{2}T'
             THEN (dados_raw->>'date')::timestamptz
             ELSE data::timestamptz END DESC,
        criado_em DESC";

    private PDO $pdo;
    private string $userId;

    /** Quantas transações NOVAS a última chamada de upsertTransacoes inseriu. */
    public int $ultimoInseridos = 0;

    public function __construct(string $userId)
    {
        $this->userId = trim($userId);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $this->userId)) {
            throw new InvalidArgumentException('userId inválido para PierreRepository.');
        }

        $this->pdo = getConnection();
        schemaOnce($this->pdo, 'pierre_user_scoped_v1', fn() => $this->ensureUserScopedSchema());
        schemaOnce($this->pdo, 'pierre_faturas_v1', fn() => $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS pierre_faturas (
                user_id         UUID          NOT NULL,
                bill_id         VARCHAR(100)  NOT NULL,
                conta_pierre_id VARCHAR(300)  NOT NULL,
                vencimento      DATE,
                fechamento      DATE,
                total           NUMERIC(15,2),
                minimo          NUMERIC(15,2),
                atualizado_em   TIMESTAMPTZ   DEFAULT NOW(),
                PRIMARY KEY (user_id, bill_id)
            )'
        ));
        schemaOnce($this->pdo, 'pierre_contas_ocultas_v1', function () {
            try {
                $this->pdo->exec(
            "UPDATE pierre_contas c SET ativo = FALSE
             WHERE (c.banco IS NULL AND LOWER(c.nome) LIKE '%carteira%')
                OR (c.tipo = 'BANK' AND c.subtipo IN ('SAVINGS_ACCOUNT', 'SAVINGS')
                    AND c.dados_raw->>'itemId' IS NOT NULL
                    AND EXISTS (SELECT 1 FROM pierre_contas c2
                                WHERE c2.user_id = c.user_id AND c2.tipo = 'BANK' AND c2.subtipo = 'CHECKING_ACCOUNT'
                                  AND c2.dados_raw->>'itemId' = c.dados_raw->>'itemId'))"
                );
            } catch (\Throwable $e) {
                // tabela ainda não existe: a sincronização já ignora essas contas
            }
        });
    }

    /* ════════════════════════════════════════════
       CONTAS
    ════════════════════════════════════════════ */

    /**
     * Upsert de contas vindas da API Pierre Finance.
     * Usa pierre_id como chave de conflito.
     * Retorna quantas linhas foram processadas.
     */
    public function upsertContas(array $contas): int
    {
        if (empty($contas)) return 0;

        $sqlUpdate = "
            UPDATE pierre_contas
            SET
                nome            = :nome,
                nome_marketing  = :nome_marketing,
                banco           = :banco,
                tipo            = :tipo,
                subtipo         = :subtipo,
                saldo           = :saldo,
                limite          = :limite,
                fatura_atual    = :fatura_atual,
                disponivel      = :disponivel,
                fechamento      = :fechamento,
                vencimento      = :vencimento,
                bandeira        = :bandeira,
                nivel           = :nivel,
                dados_raw       = :dados_raw::jsonb,
                sincronizado_em = NOW(),
                atualizado_em   = NOW()
            WHERE user_id = :user_id
              AND pierre_id = :pierre_id
        ";

        $sqlInsert = "
            INSERT INTO pierre_contas (
                user_id, pierre_id, nome, nome_marketing, banco, tipo, subtipo,
                saldo, limite, fatura_atual, disponivel,
                fechamento, vencimento, bandeira, nivel,
                dados_raw, sincronizado_em
            ) VALUES (
                :user_id, :pierre_id, :nome, :nome_marketing, :banco, :tipo, :subtipo,
                :saldo, :limite, :fatura_atual, :disponivel,
                :fechamento, :vencimento, :bandeira, :nivel,
                :dados_raw::jsonb, NOW()
            )
        ";

        $stmtUpdate = $this->pdo->prepare($sqlUpdate);
        $stmtInsert = $this->pdo->prepare($sqlInsert);
        $count = 0;

        // Contas que não devem aparecer: carteira manual da Pierre e poupança/conta secundária
        // de um banco que já tem conta corrente (ex.: Inter lista duas, a Pierre mostra uma).
        $itensComCorrente = [];
        foreach ($contas as $c) {
            if (strtoupper((string)($c['type'] ?? $c['accountType'] ?? '')) === 'BANK'
                && strtoupper((string)($c['subtype'] ?? $c['accountSubtype'] ?? '')) === 'CHECKING_ACCOUNT'
                && !empty($c['itemId'])) {
                $itensComCorrente[$c['itemId']] = true;
            }
        }
        $stmtOculta = $this->pdo->prepare('UPDATE pierre_contas SET ativo = FALSE WHERE user_id = :user_id AND pierre_id = :pierre_id');

        foreach ($contas as $c) {
            $cd      = $c['creditData'] ?? [];

            $ehBanco   = strtoupper((string)($c['type'] ?? $c['accountType'] ?? '')) === 'BANK';
            $subtipoC  = strtoupper((string)($c['subtype'] ?? $c['accountSubtype'] ?? ''));
            $conector  = $c['connectorName'] ?? $c['providerCode'] ?? null;
            $oculta = (empty($conector) && stripos((string)($c['name'] ?? $c['accountName'] ?? ''), 'carteira') !== false)
                || ($ehBanco && in_array($subtipoC, ['SAVINGS_ACCOUNT', 'SAVINGS'], true)
                    && !empty($c['itemId']) && isset($itensComCorrente[$c['itemId']]));
            if ($oculta) {
                $idOculta = $c['id'] ?? $c['accountId'] ?? $c['account_id'] ?? null;
                if ($idOculta !== null) {
                    $stmtOculta->execute([':user_id' => $this->userId, ':pierre_id' => (string)$idOculta]);
                }
                continue;
            }
            $tipo    = $c['accountType'] ?? $c['type'] ?? '';
            $subtipo = $c['accountSubtype'] ?? $c['subtype'] ?? null;

            // Gera um ID determinístico caso a API não retorne um
            $pierreId = $c['id']
                     ?? $c['accountId']
                     ?? $c['account_id']
                     ?? ($c['providerCode'] . '_' . ($c['accountSubtype'] ?? 'unknown'));

            // Fatura corrente: para cartões de crédito usamos o saldo negativo
            $saldo        = (float)($c['accountBalance'] ?? $c['balance'] ?? 0);
            $faturaAtual  = ($tipo === 'CREDIT') ? abs($saldo) : null;

            $params = [
                ':user_id'        => $this->userId,
                ':pierre_id'      => $pierreId,
                ':nome'           => $c['accountName']          ?? $c['name'] ?? null,
                ':nome_marketing' => $c['accountMarketingName'] ?? $c['marketingName'] ?? null,
                ':banco'          => $c['providerCode']
                                  ?? $c['connectorName']
                                  ?? ($c['institution']['name']  ?? null),
                ':tipo'           => $tipo ?: null,
                ':subtipo'        => $subtipo,
                ':saldo'          => $saldo,
                ':limite'         => $cd['creditLimit']          ?? null,
                ':fatura_atual'   => $faturaAtual,
                ':disponivel'     => $cd['availableCreditLimit'] ?? null,
                ':fechamento'     => $this->parseDate($cd['balanceCloseDate'] ?? null),
                ':vencimento'     => $this->parseDate($cd['balanceDueDate']   ?? null),
                ':bandeira'       => $cd['brand']                ?? null,
                ':nivel'          => $cd['level']                ?? null,
                ':dados_raw'      => json_encode($c, JSON_UNESCAPED_UNICODE),
            ];

            $stmtUpdate->execute($params);
            if ($stmtUpdate->rowCount() === 0) {
                $stmtInsert->execute($params);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Retorna contas armazenadas (opcionalmente filtradas por tipo).
     * Retorna o campo dados_raw decodificado para manter compatibilidade
     * com o formato que o frontend já espera da Pierre Finance.
     */
    public function getContas(string $tipo = ''): array
    {
        $sql    = 'SELECT * FROM pierre_contas WHERE ativo = TRUE AND user_id = :user_id';
        $params = [':user_id' => $this->userId];

        if ($tipo !== '') {
            $sql             .= ' AND tipo = :tipo';
            $params[':tipo']  = strtoupper($tipo);
        }

        $sql .= ' ORDER BY banco, nome';
        $stmt  = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows  = $stmt->fetchAll();

        // Devolve o payload original da Pierre Finance com metadados do banco
        return array_map(function ($row) {
            $raw = json_decode($row['dados_raw'] ?? '{}', true) ?? [];

            // Compatibilidade entre payload legado e payload atual da API Pierre.
            $raw['id']                   = $raw['id'] ?? $row['pierre_id'];
            $raw['accountId']            = $raw['accountId'] ?? ($raw['account_id'] ?? $row['pierre_id']);
            $raw['accountName']          = $raw['accountName'] ?? ($raw['name'] ?? $row['nome']);
            $raw['accountMarketingName'] = $raw['accountMarketingName'] ?? ($raw['marketingName'] ?? $row['nome_marketing']);
            $raw['providerCode']         = $raw['providerCode'] ?? ($raw['connectorName'] ?? $row['banco']);
            $raw['accountType']          = $raw['accountType'] ?? ($raw['type'] ?? $row['tipo']);
            $raw['accountSubtype']       = $raw['accountSubtype'] ?? ($raw['subtype'] ?? $row['subtipo']);
            $raw['accountBalance']       = isset($raw['accountBalance'])
                ? (float)$raw['accountBalance']
                : (isset($raw['balance']) ? (float)$raw['balance'] : (float)$row['saldo']);

            // Injeta campos calculados/atualizados do banco (mais recentes)
            $raw['_db_id']            = $row['id'];
            $raw['_sincronizado_em']  = $row['sincronizado_em'];
            return $raw;
        }, $rows);
    }

    /** Conta total de contas armazenadas. */
    public function countContas(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pierre_contas WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $this->userId]);
        return (int) $stmt->fetchColumn();
    }

    /* ════════════════════════════════════════════
       TRANSAÇÕES
    ════════════════════════════════════════════ */

    /**
     * Upsert de transações vindas da API Pierre Finance.
     * Usa pierre_id como chave de conflito (UNIQUE).
     */
    public function upsertTransacoes(array $transacoes): int
    {
        $this->ultimoInseridos = 0;
        if (empty($transacoes)) return 0;

        // Normaliza e remove repetidos (mesmo pierre_id) mantendo o último.
        $linhas = [];
        foreach ($transacoes as $tx) {
            $pierreId = $tx['id'] ?? $tx['transactionId'] ?? null;
            if (!$pierreId) continue; // sem ID não há como fazer upsert confiável

            $valor = (float)($tx['amount'] ?? 0);
            $linhas[(string)$pierreId] = [
                'pierre_id'       => (string)$pierreId,
                'pierre_conta_id' => $tx['accountId'] ?? ($tx['account_id'] ?? null),
                'data'            => $this->parseDate($tx['date'] ?? null) ?? date('Y-m-d'),
                'descricao'       => $tx['description'] ?? 'Sem descrição',
                'valor'           => $valor,
                'tipo'            => strtoupper($tx['type'] ?? ($valor >= 0 ? 'CREDIT' : 'DEBIT')),
                'categoria'       => $tx['category'] ?? null,
                'conta_nome'      => $tx['account_name'] ?? $tx['accountName'] ?? null,
                'conta_tipo'      => $tx['accountType'] ?? ($tx['account_type'] ?? null),
                'status'          => $tx['status'] ?? null,
                'bill_id'         => $tx['credit_card_data']['billId'] ?? null,
                'dados_raw'       => json_encode($tx, JSON_UNESCAPED_UNICODE),
            ];
        }
        if (!$linhas) return 0;

        // Uma consulta traz o que já existe: assim só inserimos o novo e só atualizamos o que mudou.
        $menorData = min(array_column($linhas, 'data'));
        $stmtExist = $this->pdo->prepare(
            "SELECT pierre_id, pierre_conta_id, data::text AS data, descricao, valor::float8 AS valor,
                    categoria, status, dados_raw->'credit_card_data'->>'billId' AS bill_id
             FROM pierre_transacoes
             WHERE user_id = :user_id AND data >= :min"
        );
        $stmtExist->execute([':user_id' => $this->userId, ':min' => date('Y-m-d', strtotime($menorData . ' -2 days'))]);
        $existentes = [];
        foreach ($stmtExist->fetchAll() as $r) {
            $existentes[$r['pierre_id']] = $r;
        }

        $novas = [];
        $mudadas = [];
        foreach ($linhas as $id => $l) {
            $e = $existentes[$id] ?? null;
            if ($e === null) {
                $novas[] = $l;
            } elseif (
                $e['pierre_conta_id'] !== $l['pierre_conta_id'] || $e['data'] !== $l['data']
                || $e['descricao'] !== $l['descricao'] || abs($e['valor'] - $l['valor']) > 0.001
                || $e['categoria'] !== $l['categoria'] || $e['status'] !== $l['status']
                || $e['bill_id'] !== $l['bill_id']
            ) {
                $mudadas[] = $l;
            }
        }

        $stmtUpdate = $this->pdo->prepare("
            UPDATE pierre_transacoes
            SET pierre_conta_id = :pierre_conta_id, data = :data, descricao = :descricao, valor = :valor,
                tipo = :tipo, categoria = :categoria, conta_nome = :conta_nome, conta_tipo = :conta_tipo,
                status = :status, dados_raw = :dados_raw::jsonb, sincronizado_em = NOW()
            WHERE user_id = :user_id AND pierre_id = :pierre_id
        ");

        $this->pdo->beginTransaction();
        try {
            foreach ($mudadas as $l) {
                $stmtUpdate->execute([
                    ':user_id' => $this->userId, ':pierre_id' => $l['pierre_id'],
                    ':pierre_conta_id' => $l['pierre_conta_id'], ':data' => $l['data'],
                    ':descricao' => $l['descricao'], ':valor' => $l['valor'], ':tipo' => $l['tipo'],
                    ':categoria' => $l['categoria'], ':conta_nome' => $l['conta_nome'],
                    ':conta_tipo' => $l['conta_tipo'], ':status' => $l['status'], ':dados_raw' => $l['dados_raw'],
                ]);
            }

            // Inserção em lotes de 50 linhas: 1 ida ao banco por lote em vez de 1 por transação.
            foreach (array_chunk($novas, 50) as $lote) {
                $valores = [];
                $params = [':user_id' => $this->userId];
                foreach ($lote as $i => $l) {
                    $valores[] = "(:user_id, :p{$i}, :c{$i}, :d{$i}, :ds{$i}, :v{$i}, :t{$i}, :cat{$i}, :cn{$i}, :ct{$i}, :s{$i}, :raw{$i}::jsonb, NOW())";
                    $params[":p{$i}"] = $l['pierre_id'];
                    $params[":c{$i}"] = $l['pierre_conta_id'];
                    $params[":d{$i}"] = $l['data'];
                    $params[":ds{$i}"] = $l['descricao'];
                    $params[":v{$i}"] = $l['valor'];
                    $params[":t{$i}"] = $l['tipo'];
                    $params[":cat{$i}"] = $l['categoria'];
                    $params[":cn{$i}"] = $l['conta_nome'];
                    $params[":ct{$i}"] = $l['conta_tipo'];
                    $params[":s{$i}"] = $l['status'];
                    $params[":raw{$i}"] = $l['dados_raw'];
                }
                $this->pdo->prepare(
                    'INSERT INTO pierre_transacoes (
                        user_id, pierre_id, pierre_conta_id, data, descricao, valor,
                        tipo, categoria, conta_nome, conta_tipo, status, dados_raw, sincronizado_em
                     ) VALUES ' . implode(', ', $valores)
                )->execute($params);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        $this->ultimoInseridos = count($novas);
        return count($linhas);
    }

    /**
     * Retorna transações filtradas, devolvendo o payload original da Pierre.
     *
     * Filtros aceitos:
     *   startDate, endDate, accountType, tipo (CREDIT|DEBIT),
     *   categoria, minAmount, maxAmount, limit
     */
    public function getTransacoes(array $filtros = []): array
    {
        $where  = ['user_id = :user_id'];
        $params = [':user_id' => $this->userId];

        if (!empty($filtros['startDate'])) {
            $where[]         = 'data >= :start';
            $params[':start'] = $filtros['startDate'];
        }
        if (!empty($filtros['endDate'])) {
            $where[]        = 'data <= :end';
            $params[':end']  = $filtros['endDate'];
        }
        if (!empty($filtros['accountType'])) {
            $where[]              = 'conta_tipo = :conta_tipo';
            $params[':conta_tipo'] = strtoupper($filtros['accountType']);
        }
        if (!empty($filtros['tipo'])) {
            $where[]        = 'tipo = :tipo';
            $params[':tipo'] = strtoupper($filtros['tipo']);
        }
        if (!empty($filtros['categoria'])) {
            $where[]            = 'LOWER(categoria) LIKE :categoria';
            $params[':categoria'] = '%' . strtolower($filtros['categoria']) . '%';
        }
        if (isset($filtros['minAmount'])) {
            $where[]      = 'ABS(valor) >= :min';
            $params[':min'] = abs((float)$filtros['minAmount']);
        }
        if (isset($filtros['maxAmount'])) {
            $where[]      = 'ABS(valor) <= :max';
            $params[':max'] = abs((float)$filtros['maxAmount']);
        }

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql      = "SELECT * FROM pierre_transacoes {$whereStr} ORDER BY " . self::ORDEM_RECENTE . "";

        if (!empty($filtros['limit'])) {
            $sql .= ' LIMIT ' . (int)$filtros['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Faturas fechadas: servem para reconhecer pagamento de fatura feito por PIX/transferência.
        $faturas = [];
        try {
            $fs = $this->pdo->prepare('SELECT total::float8 AS total, fechamento::text AS fechamento, vencimento::text AS vencimento FROM pierre_faturas WHERE user_id = :u');
            $fs->execute([':u' => $this->userId]);
            $faturas = $fs->fetchAll();
        } catch (\Throwable $e) {
            // tabela ainda não existe
        }

        $ignorados = [];
        try {
            $ds = $this->pdo->prepare('SELECT destino FROM destinos_ignorados WHERE user_id = :u');
            $ds->execute([':u' => $this->userId]);
            $ignorados = array_map('mb_strtolower', $ds->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            // tabela criada na primeira vez que a tela de Metas é aberta
        }

        return array_map(function ($row) use ($faturas, $ignorados) {
            $raw = json_decode($row['dados_raw'] ?? '{}', true) ?? [];

            $raw['id']           = $raw['id'] ?? $row['pierre_id'];
            $raw['accountId']    = $raw['accountId'] ?? ($raw['account_id'] ?? $row['pierre_conta_id']);
            $raw['account_type'] = $raw['account_type'] ?? ($raw['accountType'] ?? $row['conta_tipo']);
            $raw['accountType']  = $raw['accountType'] ?? ($raw['account_type'] ?? $row['conta_tipo']);
            $raw['account_name'] = $raw['account_name'] ?? ($raw['accountName'] ?? $row['conta_nome']);
            $raw['description']  = $raw['description'] ?? $row['descricao'];
            $raw['category']     = $raw['category'] ?? $row['categoria'];
            $raw['amount']       = isset($raw['amount']) ? (float)$raw['amount'] : (float)$row['valor'];
            $raw['type']         = $raw['type'] ?? $row['tipo'];
            $raw['date']         = $raw['date'] ?? $row['data'];

            $raw['_db_id']           = $row['id'];
            $raw['_sincronizado_em'] = $row['sincronizado_em'];
            $raw['_classe']          = classeTransacao($raw, $faturas, $ignorados);   // receita | despesa | interno
            return $raw;
        }, $rows);
    }

    /** Contagem total de transações armazenadas. */
    public function countTransacoes(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pierre_transacoes WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $this->userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna saldo consolidado das contas bancárias (tipo = BANK).
     */
    public function getSaldoTotal(): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(saldo), 0)
             FROM pierre_contas
             WHERE tipo = 'BANK' AND ativo = TRUE AND user_id = :user_id"
        );
        $stmt->execute([':user_id' => $this->userId]);
        $val = $stmt->fetchColumn();
        return (float) $val;
    }

    /**
     * Retorna saldo por conta bancária (para o endpoint /saldo).
     */
    public function getSaldoPorConta(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pierre_id, nome, banco, saldo, dados_raw
             FROM pierre_contas
             WHERE tipo = 'BANK' AND ativo = TRUE AND user_id = :user_id
             ORDER BY banco, nome"
        );
        $stmt->execute([':user_id' => $this->userId]);
        return array_map(function ($row) {
            $raw = json_decode($row['dados_raw'] ?? '{}', true) ?? [];
            $raw['_saldo_db'] = $row['saldo'];
            return $raw;
        }, $stmt->fetchAll());
    }

    /* ════════════════════════════════════════════
       ASSINATURAS
    ════════════════════════════════════════════ */

    /** Categorias da Pierre onde cobrança recorrente costuma ser assinatura. */
    private const ASSIN_CAT_OK = '/servi[cç]os digitais|streaming|assinatura|telecomunica|seguro|bem-estar|educa[cç]|academia|software|m[uú]sica|jogos|entreten/iu';
    /** Categorias que nunca são assinatura (compras do dia a dia, taxas, transferências…). */
    private const ASSIN_CAT_NAO = '/transfer|pix|supermercado|restaurante|delivery|posto|estacionamento|farm[aá]cia|sa[uú]de$|juros|imposto|atraso|cheque especial|pagamento de cart|investiment|empr[eé]stimo|taxa|vestu|aposta|t[aá]xi|ped[aá]gio|alimentos|hospedagem|estorno|eletr[oô]nicos|livraria|manuten|automotiv|compras|utens|combust|utilidade p[uú]blica/iu';
    private const ASSIN_DESC_NAO = '/encargo|iof|juros|multa|rotativo|pagamento efetuado|transfer|pix|boleto|saque|aplica[cç]|resgate|estorno|cofrinho|reservado/iu';
    /** Marcas conhecidas: valem mesmo com categoria genérica/errada e dão nome à assinatura. */
    private const ASSIN_MARCAS = '/netflix|spotify|disney|hbo|\bmax\b|prime ?video|amazon ?prime|youtube|apple|google|icloud|microsoft|office ?365|adobe|canva|chatgpt|openai|anthropic|claude|github|notion|dropbox|linkedin|duolingo|deezer|globoplay|paramount|crunchyroll|meli\+|uber ?one|smart ?fit|bluefit|gympass|wellhub|\bvivo\b|\bclaro\b|\btim\b|\boi\b|\bsky\b|tidal|telecine|premiere|cursor|figma|slack|zoom|midjourney|replit|vercel|hostinger|godaddy|locaweb|cloudflare/u';
    /** Marcas de serviço pessoal onde UMA cobrança recente já indica assinatura mensal. */
    private const ASSIN_UNICA = '/netflix|spotify|disney|hbo|youtube|globoplay|paramount|crunchyroll|deezer|chatgpt|openai|anthropic|claude|icloud|apple|duolingo|canva|notion|adobe|cursor|midjourney/u';

    private function semAcento(string $t): string
    {
        return strtr(mb_strtolower($t), ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
    }

    /** Estabelecimento sem marca conhecida: 2 primeiras palavras úteis (sem números nem códigos). */
    private function chaveComerciante(string $descricao): string
    {
        $t = preg_replace('/^.*\|/u', '', $this->semAcento($descricao));
        $t = preg_replace('/[^a-z+ ]+/', ' ', $t);
        $stop = ['com','www','br','ltda','sa','pix','compra','debito','credito','cartao','pagamento','sao','paulo','san','fra','sub','the','inc','visa','electron','brasil'];
        $tokens = array_values(array_filter(preg_split('/\s+/', trim($t)) ?: [], static fn($w) => strlen($w) >= 3 && !in_array($w, $stop, true)));
        return implode(' ', array_slice($tokens, 0, 2));
    }

    private function nomeAssinatura(?string $marca, string $descricao): string
    {
        if ($marca === null) {
            return trim(preg_replace('/\s+/', ' ', $descricao));
        }
        $mapa = [
            'youtube' => 'YouTube Premium', 'google' => 'Google', 'apple' => 'Apple (App Store/iCloud)',
            'anthropic' => 'Claude (Anthropic)', 'claude' => 'Claude (Anthropic)',
            'openai' => 'ChatGPT (OpenAI)', 'chatgpt' => 'ChatGPT (OpenAI)', 'github' => 'GitHub',
            'smartfit' => 'Smart Fit', 'bluefit' => 'Bluefit',
        ];
        if ($marca === 'google' && stripos($descricao, 'youtub') !== false) return 'YouTube Premium';
        return $mapa[$marca] ?? ucfirst($marca);
    }

    /**
     * Detecta assinaturas nas transações armazenadas.
     *
     * Regras: mesmo estabelecimento/marca; categoria de serviço recorrente OU marca conhecida;
     * cobranças separadas por valor (±6%); cadência mensal/trimestral/anual; ignora parcelamentos,
     * juros/IOF/encargos e transferências; só assinaturas ainda ativas (última cobrança recente).
     */
    public function detectarAssinaturas(): int
    {
        $this->ensureAssinaturasTable();
        $this->ensureAssinaturasIgnoradasTable();

        $stmt = $this->pdo->prepare(
            "SELECT data::text AS data, descricao, ABS(valor)::float8 AS valor, categoria, conta_nome, conta_tipo,
                    dados_raw->>'connector_name' AS banco, dados_raw->>'connector_image_url' AS icone,
                    dados_raw->'credit_card_data'->>'totalInstallments' AS total_parcelas
             FROM pierre_transacoes
             WHERE user_id = :u AND tipo = 'DEBIT' AND TRIM(descricao) <> ''
               AND data >= CURRENT_DATE - INTERVAL '400 days'
             ORDER BY data ASC"
        );
        $stmt->execute([':u' => $this->userId]);

        $grupos = [];
        foreach ($stmt->fetchAll() as $r) {
            if ((int)($r['total_parcelas'] ?? 0) > 1) continue;                       // compra parcelada
            $cat  = (string)($r['categoria'] ?? '');
            $desc = $this->semAcento($r['descricao']);
            if (preg_match(self::ASSIN_DESC_NAO, $desc)) continue;

            $achou = preg_match(self::ASSIN_MARCAS, $desc, $m) === 1;
            if (!$achou && (preg_match(self::ASSIN_CAT_NAO, $cat) || !preg_match(self::ASSIN_CAT_OK, $cat))) continue;

            $marca = $achou ? preg_replace('/\W+/', '', $m[0]) : null;
            $chave = $marca ?? $this->chaveComerciante($r['descricao']);
            if ($chave === '') continue;

            $r['marca']  = $marca;
            $r['unica']  = (bool)preg_match(self::ASSIN_UNICA, $desc);
            $r['cat_ok'] = (bool)preg_match(self::ASSIN_CAT_OK, $cat);
            $grupos[$chave][] = $r;
        }

        $ign = $this->pdo->prepare('SELECT LOWER(TRIM(descricao)) AS d, ROUND(valor::numeric, 2)::float8 AS v FROM pierre_assinaturas_ignoradas WHERE user_id = :u');
        $ign->execute([':u' => $this->userId]);
        $ignoradas = [];
        foreach ($ign->fetchAll() as $i) $ignoradas[$i['d'] . '|' . number_format((float)$i['v'], 2, '.', '')] = true;

        $hoje = new DateTimeImmutable('today');
        $novas = [];

        foreach ($grupos as $cobrancas) {
            // Separa serviços diferentes da mesma marca pelo valor (ex.: dois planos da Apple).
            usort($cobrancas, static fn($a, $b) => $a['valor'] <=> $b['valor']);
            $clusters = [];
            foreach ($cobrancas as $c) {
                $ultimo = count($clusters) - 1;
                if ($ultimo >= 0 && $c['valor'] <= $clusters[$ultimo][0]['valor'] * 1.06) $clusters[$ultimo][] = $c;
                else $clusters[] = [$c];
            }

            foreach ($clusters as $cl) {
                $porDia = [];                                    // junta cobranças do mesmo dia
                foreach ($cl as $c) $porDia[$c['data']] = $c;
                ksort($porDia);
                $cl = array_values($porDia);
                $n = count($cl);
                $ultima = $cl[$n - 1];
                $dataUltima = new DateTimeImmutable($ultima['data']);
                $dias = (int)$dataUltima->diff($hoje)->format('%r%a');

                if ($n === 1) {
                    // Uma única cobrança recente de serviço pessoal conhecido já indica assinatura mensal.
                    if (!($ultima['unica'] && $dias >= 0 && $dias <= 35)) continue;
                    $per = 'MONTHLY'; $passo = '+1 month';
                } else {
                    $gaps = [];
                    for ($i = 1; $i < $n; $i++) {
                        $gaps[] = (new DateTimeImmutable($cl[$i - 1]['data']))->diff(new DateTimeImmutable($cl[$i]['data']))->days;
                    }
                    sort($gaps);
                    $mediana = $gaps[intdiv(count($gaps), 2)];

                    if ($mediana >= 25 && $mediana <= 36)       { $per = 'MONTHLY';   $passo = '+1 month';  $tol = 45; }
                    elseif ($mediana >= 350 && $mediana <= 380) { $per = 'YEARLY';    $passo = '+1 year';   $tol = 400; }
                    elseif ($mediana >= 85 && $mediana <= 95)   { $per = 'QUARTERLY'; $passo = '+3 months'; $tol = 110; }
                    else continue;

                    if ($n < 3 && $ultima['marca'] === null && !$ultima['cat_ok']) continue;
                    if ($dias > $tol) continue;                  // já cancelada
                }

                $proxima = $dataUltima->modify($passo);
                for ($k = 0; $proxima < $hoje && $k < 24; $k++) $proxima = $proxima->modify($passo);

                $nome = $this->nomeAssinatura($ultima['marca'], $ultima['descricao']);
                if (isset($ignoradas[mb_strtolower($nome) . '|' . number_format((float)$ultima['valor'], 2, '.', '')])) continue;

                $novas[] = [
                    'descricao' => $nome, 'valor' => round((float)$ultima['valor'], 2), 'per' => $per,
                    'ultima' => $ultima['data'], 'proxima' => $proxima->format('Y-m-d'),
                    'categoria' => $ultima['categoria'], 'conta_nome' => $ultima['conta_nome'],
                    'conta_tipo' => $ultima['conta_tipo'], 'banco' => $ultima['banco'], 'icone' => $ultima['icone'],
                ];
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM pierre_assinaturas WHERE user_id = :u')->execute([':u' => $this->userId]);
            $ins = $this->pdo->prepare(
                'INSERT INTO pierre_assinaturas
                    (user_id, descricao, valor, periodicidade, ultima_cobranca, proxima_cobranca,
                     categoria, conta_nome, conta_tipo, banco, icone_url)
                 VALUES (:u, :d, :v, :p, :ul, :px, :cat, :cn, :ct, :b, :i)
                 ON CONFLICT DO NOTHING'
            );
            foreach ($novas as $a) {
                $ins->execute([
                    ':u' => $this->userId, ':d' => $a['descricao'], ':v' => $a['valor'], ':p' => $a['per'],
                    ':ul' => $a['ultima'], ':px' => $a['proxima'], ':cat' => $a['categoria'],
                    ':cn' => $a['conta_nome'], ':ct' => $a['conta_tipo'], ':b' => $a['banco'], ':i' => $a['icone'],
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        return count($novas);
    }

    /**
     * Marca assinatura como indevida para não reaparecer em futuras sincronizações.
     */
    public function marcarAssinaturaIndevida(string $descricao, float $valor): bool
    {
        $this->ensureAssinaturasIgnoradasTable();

        $descricao = trim($descricao);
        if ($descricao === '') return false;

          $stmt = $this->pdo->prepare(
              'INSERT INTO pierre_assinaturas_ignoradas (user_id, descricao, valor)
               SELECT :user_id, :descricao, :valor
               WHERE NOT EXISTS (
                 SELECT 1
                 FROM pierre_assinaturas_ignoradas
                 WHERE user_id = :user_id
                   AND LOWER(TRIM(descricao)) = LOWER(TRIM(:descricao))
                   AND ROUND(valor::numeric, 2) = ROUND(:valor::numeric, 2)
               )'
          );

        $ok = $stmt->execute([
            ':user_id'   => $this->userId,
            ':descricao' => $descricao,
            ':valor'     => round(abs($valor), 2),
        ]);

        $up = $this->pdo->prepare(
            'UPDATE pierre_assinaturas
             SET ativa = FALSE, atualizado_em = NOW()
                         WHERE user_id = :user_id
                             AND LOWER(TRIM(descricao)) = LOWER(TRIM(:descricao))
               AND ROUND(valor::numeric, 2) = ROUND(:valor::numeric, 2)'
        );
        $up->execute([
                        ':user_id'   => $this->userId,
            ':descricao' => $descricao,
            ':valor'     => round(abs($valor), 2),
        ]);

        return $ok;
    }

    public function assinaturaIgnorada(string $descricao, float $valor): bool
    {
        $this->ensureAssinaturasIgnoradasTable();

        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM pierre_assinaturas_ignoradas
                         WHERE user_id = :user_id
                             AND LOWER(TRIM(descricao)) = LOWER(TRIM(:descricao))
               AND ROUND(valor::numeric, 2) = ROUND(:valor::numeric, 2)
             LIMIT 1'
        );

        $stmt->execute([
                        ':user_id'   => $this->userId,
            ':descricao' => trim($descricao),
            ':valor'     => round(abs($valor), 2),
        ]);

        return (bool)$stmt->fetchColumn();
    }

    /* ════════════════════════════════════════════
       DESPESAS PREVISTAS E ASSINATURAS MANUAIS
    ════════════════════════════════════════════ */

    public function criarDespesaPrevista(string $descricao, string $primeiraCobrancaMes, int $duracaoMeses, float $valorParcela): bool
    {
        $this->ensureDespesasPrevistasTable();

        $descricao = trim($descricao);
        if ($descricao === '' || $duracaoMeses <= 0 || $valorParcela <= 0) return false;

        $mesNormalizado = $this->normalizarMesReferencia($primeiraCobrancaMes);
        if ($mesNormalizado === null) {
            return false;
        }

        $primeiraCobranca = $mesNormalizado . '-01';
        $id = $this->uuidV4();

        $stmt = $this->pdo->prepare(
            'INSERT INTO pierre_despesas_previstas (id, user_id, descricao, primeira_cobranca, duracao_meses, valor_mensal)
             VALUES (:id, :user_id, :descricao, :primeira_cobranca, :duracao_meses, :valor_mensal)'
        );

        return $stmt->execute([
            ':id'               => $id,
            ':user_id'          => $this->userId,
            ':descricao'         => $descricao,
            ':primeira_cobranca' => $primeiraCobranca,
            ':duracao_meses'     => $duracaoMeses,
            ':valor_mensal'      => round($valorParcela, 2),
        ]);
    }

    public function atualizarDespesaPrevista(string $id, string $descricao, string $primeiraCobrancaMes, int $duracaoMeses, float $valorParcela): bool
    {
        $this->ensureDespesasPrevistasTable();

        $id = trim($id);
        $descricao = trim($descricao);
        if ($id === '' || $descricao === '' || $duracaoMeses <= 0 || $valorParcela <= 0) return false;

        $mesNormalizado = $this->normalizarMesReferencia($primeiraCobrancaMes);
        if ($mesNormalizado === null) {
            return false;
        }

        $primeiraCobranca = $mesNormalizado . '-01';

        $stmt = $this->pdo->prepare(
            'UPDATE pierre_despesas_previstas
             SET descricao = :descricao,
                 primeira_cobranca = :primeira_cobranca,
                 duracao_meses = :duracao_meses,
                 valor_mensal = :valor_mensal
                         WHERE id = :id
                             AND user_id = :user_id
               AND duracao_meses IS NOT NULL'
        );

        $stmt->execute([
            ':id'               => $id,
            ':user_id'          => $this->userId,
            ':descricao'        => $descricao,
            ':primeira_cobranca'=> $primeiraCobranca,
            ':duracao_meses'    => $duracaoMeses,
            ':valor_mensal'     => round($valorParcela, 2),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function excluirDespesaPrevista(string $id): bool
    {
        $this->ensureDespesasPrevistasTable();

        $id = trim($id);
        if ($id === '') return false;

        $stmt = $this->pdo->prepare(
            'DELETE FROM pierre_despesas_previstas
                         WHERE id = :id
                             AND user_id = :user_id
               AND duracao_meses IS NOT NULL'
        );

                $stmt->execute([':id' => $id, ':user_id' => $this->userId]);

        return $stmt->rowCount() > 0;
    }

    public function criarAssinaturaManual(string $descricao, string $primeiraCobrancaMes, float $valorMensal, ?string $banco = null, ?string $contaTipo = null): bool
    {
        $this->ensureDespesasPrevistasTable();

        $descricao = trim($descricao);
        if ($descricao === '' || $valorMensal <= 0) return false;

        $mesNormalizado = $this->normalizarMesReferencia($primeiraCobrancaMes);
        if ($mesNormalizado === null) {
            return false;
        }

        $primeiraCobranca = $mesNormalizado . '-01';
        $id = $this->uuidV4();

        $stmt = $this->pdo->prepare(
            'INSERT INTO pierre_despesas_previstas (id, user_id, descricao, primeira_cobranca, duracao_meses, valor_mensal, ativa, banco, conta_tipo)
             VALUES (:id, :user_id, :descricao, :primeira_cobranca, NULL, :valor_mensal, TRUE, :banco, :conta_tipo)'
        );

        $banco = $banco !== null ? trim($banco) : '';
        $contaTipo = strtoupper(trim((string)$contaTipo));

        return $stmt->execute([
            ':id'               => $id,
            ':user_id'          => $this->userId,
            ':descricao'        => $descricao,
            ':primeira_cobranca'=> $primeiraCobranca,
            ':valor_mensal'     => round($valorMensal, 2),
            ':banco'            => $banco !== '' ? mb_substr($banco, 0, 120) : null,
            ':conta_tipo'       => in_array($contaTipo, ['CREDIT', 'BANK'], true) ? $contaTipo : null,
        ]);
    }

    public function atualizarStatusAssinaturaManual(string $id, bool $ativa): bool
    {
        $this->ensureDespesasPrevistasTable();

        $id = trim($id);
        if ($id === '') return false;

        $stmt = $this->pdo->prepare(
            'UPDATE pierre_despesas_previstas
             SET ativa = :ativa
                         WHERE id = :id
                             AND user_id = :user_id
               AND duracao_meses IS NULL'
        );

        // PDO envia false como texto vazio e o PostgreSQL recusa: boolean precisa de bind explícito.
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':user_id', $this->userId);
        $stmt->bindValue(':ativa', $ativa, PDO::PARAM_BOOL);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function getDespesasPrevistas(bool $somenteAtivas = true, ?string $mesReferencia = null): array
    {
        $this->ensureDespesasPrevistasTable();

        $mesFiltrado = null;
        if ($mesReferencia !== null && trim($mesReferencia) !== '') {
            $mesFiltrado = $this->normalizarMesReferencia($mesReferencia);
            if ($mesFiltrado === null) {
                return [];
            }
        }

        $sql = "
            SELECT
                id,
                descricao,
                primeira_cobranca,
                duracao_meses,
                COALESCE(valor_mensal, 0) AS valor_parcela,
                ativa,
                criado_em,
                (primeira_cobranca + ((duracao_meses - 1) * INTERVAL '1 month'))::date AS ultima_cobranca,
                (primeira_cobranca + ((duracao_meses - 1) * INTERVAL '1 month'))::date AS encerramento_previsto
            FROM pierre_despesas_previstas
                        WHERE user_id = :user_id
                            AND duracao_meses IS NOT NULL
        ";

        if ($somenteAtivas) {
            $sql .= ' AND ativa = TRUE';
        }

        if ($mesFiltrado !== null) {
            $sql .= "
               AND DATE_TRUNC('month', TO_DATE(:mes_referencia, 'YYYY-MM'))::date >= DATE_TRUNC('month', primeira_cobranca)::date
               AND DATE_TRUNC('month', TO_DATE(:mes_referencia, 'YYYY-MM'))::date <= DATE_TRUNC('month', (primeira_cobranca + ((duracao_meses - 1) * INTERVAL '1 month'))::date)::date
            ";
        }

        $sql .= ' ORDER BY primeira_cobranca DESC, criado_em DESC';

        $stmt = $this->pdo->prepare($sql);
        $params = [':user_id' => $this->userId];
        if ($mesFiltrado !== null) {
            $params[':mes_referencia'] = $mesFiltrado;
        }

        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /* ════════════════════════════════════════════
       FATURAS DO CARTÃO (ciclos reais)
    ════════════════════════════════════════════ */

    /** Salva as faturas fechadas informadas pela Pierre (get-bills): vencimento, fechamento e total. */
    public function upsertFaturas(array $bills): int
    {
        $linhas = [];
        foreach ($bills as $b) {
            $id = $b['id'] ?? null;
            if (!$id || empty($b['accountId'])) continue;
            $linhas[(string)$id] = [
                'bill_id'    => (string)$id,
                'conta'      => (string)$b['accountId'],
                'vencimento' => !empty($b['dueDate']) ? substr((string)$b['dueDate'], 0, 10) : null,
                'fechamento' => !empty($b['billClosingDate']) ? substr((string)$b['billClosingDate'], 0, 10) : null,
                'total'      => (float)($b['totalAmount'] ?? 0),
                'minimo'     => isset($b['minimumPaymentAmount']) ? (float)$b['minimumPaymentAmount'] : null,
            ];
        }
        if (!$linhas) return 0;

        $this->pdo->beginTransaction();
        try {
            foreach (array_chunk(array_values($linhas), 50) as $lote) {
                $valores = [];
                $params = [':u' => $this->userId];
                foreach ($lote as $i => $l) {
                    $valores[] = "(:u, :b{$i}, :c{$i}, :v{$i}, :f{$i}, :t{$i}, :m{$i}, NOW())";
                    $params[":b{$i}"] = $l['bill_id'];
                    $params[":c{$i}"] = $l['conta'];
                    $params[":v{$i}"] = $l['vencimento'];
                    $params[":f{$i}"] = $l['fechamento'];
                    $params[":t{$i}"] = $l['total'];
                    $params[":m{$i}"] = $l['minimo'];
                }
                $this->pdo->prepare(
                    'INSERT INTO pierre_faturas (user_id, bill_id, conta_pierre_id, vencimento, fechamento, total, minimo, atualizado_em)
                     VALUES ' . implode(', ', $valores) . '
                     ON CONFLICT (user_id, bill_id) DO UPDATE SET
                        conta_pierre_id = EXCLUDED.conta_pierre_id, vencimento = EXCLUDED.vencimento,
                        fechamento = EXCLUDED.fechamento, total = EXCLUDED.total, minimo = EXCLUDED.minimo, atualizado_em = NOW()'
                )->execute($params);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        return count($linhas);
    }

    /** Dia $dia do mês de $ref deslocado em $delta meses (ajustado ao último dia do mês). */
    private function diaDoMes(DateTimeImmutable $ref, int $delta, int $dia): DateTimeImmutable
    {
        $m = new DateTimeImmutable($ref->format('Y-m-01'), $ref->getTimezone());
        $m = $delta === 0 ? $m : $m->modify(($delta > 0 ? '+' : '') . $delta . ' months');
        return $m->setDate((int)$m->format('Y'), (int)$m->format('n'), min($dia, (int)$m->format('t')));
    }

    /**
     * Ciclos de fatura de um cartão: anterior, atual (a que vence primeiro) e próxima.
     *
     * Fechamento: vem das faturas reais (get-bills); o dia do fechamento se repete todo mês.
     * Compra feita NO dia do fechamento (ou depois) entra na fatura SEGUINTE, como confirmam os dados.
     * Cada item usa, nesta ordem: a fatura que o banco informou (billId); o vencimento da parcela
     * (compras parceladas); a data da compra contra o fechamento. A etiqueta billForecastDate da Pierre
     * não é usada: ela agrupa compras na fatura errada.
     * Retorna null quando ainda não há faturas sincronizadas para o cartão.
     */
    private function ciclosDoCartao(string $pierreContaId): ?array
    {
        static $cache = [];
        $chave = $this->userId . '|' . $pierreContaId;
        if (array_key_exists($chave, $cache)) return $cache[$chave];

        $c = $this->pdo->prepare(
            "SELECT pierre_id, nome, nome_marketing, banco, saldo::float8 AS saldo, fatura_atual::float8 AS fatura_atual, vencimento::text AS vencimento
             FROM pierre_contas WHERE user_id = :u AND pierre_id = :pid AND tipo = 'CREDIT' LIMIT 1"
        );
        $c->execute([':u' => $this->userId, ':pid' => $pierreContaId]);
        $conta = $c->fetch();
        if (!$conta) return $cache[$chave] = null;

        $fb = $this->pdo->prepare(
            'SELECT bill_id, vencimento::text AS vencimento, fechamento::text AS fechamento, total::float8 AS total
             FROM pierre_faturas WHERE user_id = :u AND conta_pierre_id = :pid
             ORDER BY fechamento DESC NULLS LAST'
        );
        $fb->execute([':u' => $this->userId, ':pid' => $pierreContaId]);
        $faturas = $fb->fetchAll();
        if (!$faturas || empty($faturas[0]['fechamento'])) return $cache[$chave] = null;

        // Tudo em horário de Brasília, independente do fuso do servidor (a comparação com o dia do fechamento é exata).
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $hoje = new DateTimeImmutable('today', $fuso);
        $dia  = (int)substr($faturas[0]['fechamento'], 8, 2);   // o dia do fechamento se repete todo mês

        // Vencimento da fatura "atual": o informado pela conta; se estiver vencido há tempo, avança mês a mês.
        $due = !empty($conta['vencimento']) ? new DateTimeImmutable(substr($conta['vencimento'], 0, 10), $fuso)
                                            : (new DateTimeImmutable($faturas[0]['vencimento'] ?: $faturas[0]['fechamento'], $fuso))->modify('+1 month');
        for ($k = 0; $due < $hoje->modify('-2 days') && $k < 12; $k++) $due = $due->modify('+1 month');

        // Fechamento da fatura que vence em $due: último dia $dia que cai até ~12 dias antes do vencimento.
        $C0 = null;
        foreach ([0, -1] as $d) {
            $cand = $this->diaDoMes($due, $d, $dia);
            if ($cand < $due && (int)$cand->diff($due)->days <= 12) { $C0 = $cand; break; }
        }
        $C0 = $C0 ?? $due->modify('-7 days');
        $Cm1 = $this->diaDoMes($C0, -1, $dia);
        $Cm2 = $this->diaDoMes($C0, -2, $dia);
        $dues = [
            'anterior' => $this->diaDoMes($due, -1, (int)$due->format('j')),
            'atual'    => $due,
            'proxima'  => $this->diaDoMes($due, 1, (int)$due->format('j')),
        ];
        $faturaPorId = [];
        foreach ($faturas as $f) $faturaPorId[$f['bill_id']] = $f;
        // Mês (AAAA-MM) de fechamento de cada ciclo, que é como a Pierre etiqueta as parcelas.
        $mesesCiclo = [
            'anterior' => $Cm1->format('Y-m'),
            'atual'    => $C0->format('Y-m'),
            'proxima'  => $this->diaDoMes($C0, 1, $dia)->format('Y-m'),
        ];

        $tx = $this->pdo->prepare(
            "SELECT pierre_id, data::text AS data, descricao, valor::float8 AS valor, tipo, categoria, status,
                    dados_raw->>'date' AS ts, dados_raw->>'installment_due_date' AS iv,
                    dados_raw->'credit_card_data'->>'billId' AS bill_id,
                    dados_raw->'credit_card_data'->>'billForecastDate' AS forecast,
                    dados_raw->'credit_card_data'->>'installmentNumber' AS parcela,
                    dados_raw->'credit_card_data'->>'totalInstallments' AS total_parcelas
             FROM pierre_transacoes
             WHERE user_id = :u AND pierre_conta_id = :pid AND data >= :min
               AND NOT (tipo = 'CREDIT' AND (LOWER(descricao) ~ 'pagamento|pgto|pgt |fatura|deb\\. autom'
                                              OR LOWER(COALESCE(categoria, '')) LIKE '%pagamento de cart%'))
             ORDER BY " . self::ORDEM_RECENTE
        );
        $tx->execute([':u' => $this->userId, ':pid' => $pierreContaId, ':min' => $Cm2->modify('-130 days')->format('Y-m-d')]);

        $paraData = static function (?string $iso) use ($fuso): ?DateTimeImmutable {
            if (!$iso) return null;
            try { return (new DateTimeImmutable($iso))->setTimezone($fuso)->setTime(0, 0); } catch (\Throwable $e) { return null; }
        };

        $ciclos = ['anterior' => [], 'atual' => [], 'proxima' => []];
        foreach ($tx->fetchAll() as $r) {
            $dCompra = $paraData($r['ts']) ?? new DateTimeImmutable($r['data'], $fuso);
            $parcelado = !empty($r['parcela']) && (int)$r['total_parcelas'] > 1;
            $ciclo = null;
            $fonte = 'data';

            if (!empty($r['bill_id'])) {
                // 1) O banco já amarrou o item a uma fatura: é a fonte mais confiável (bate o total em centavos).
                $fonte = 'banco';
                if (isset($faturaPorId[$r['bill_id']]) && !empty($faturaPorId[$r['bill_id']]['vencimento'])) {
                    $venc = new DateTimeImmutable($faturaPorId[$r['bill_id']]['vencimento'], $fuso);
                    foreach ($dues as $nome => $dv) {
                        if (abs((int)$venc->diff($dv)->format('%r%a')) <= 4) { $ciclo = $nome; break; }
                    }
                    if ($ciclo === null) continue;               // fatura mais antiga que o período mostrado
                } else {
                    $ciclo = 'atual';                            // fatura nova que o banco ainda não listou
                }
            } elseif ($parcelado && !empty($r['forecast'])) {
                // 2) Parcela: a Pierre informa o mês de fechamento da fatura em que ela cai.
                $fonte = 'parcela';
                foreach ($mesesCiclo as $nome => $mesTag) {
                    if ($mesTag === $r['forecast']) { $ciclo = $nome; break; }
                }
                if ($ciclo === null) continue;
            } else {
                // 3) Ainda sem fatura: data da compra contra o fechamento. No dia do fechamento já é a próxima fatura.
                $encargo = (bool)preg_match('/encargo|iof|juros|multa|rotativo/i', $r['descricao']);
                if ($dCompra >= $C0 && !($encargo && $dCompra == $C0)) $ciclo = 'proxima';
                elseif ($dCompra >= $Cm1)                              $ciclo = 'atual';
                elseif ($dCompra >= $Cm2)                              $ciclo = 'anterior';
            }
            if ($C0 > $hoje && $ciclo === 'proxima') $ciclo = 'atual';   // fatura atual ainda aberta
            if ($ciclo === null) continue;

            $r['valor'] = abs((float)$r['valor']);
            $ciclos[$ciclo][] = [
                'pierre_id' => $r['pierre_id'], 'descricao' => $r['descricao'], 'valor' => $r['valor'],
                'tipo' => $r['tipo'], 'categoria' => $r['categoria'], 'status' => $r['status'],
                'data' => $dCompra->format('Y-m-d'), 'hora_ts' => $r['ts'],
                'cobranca_em' => null,
                'parcela' => $r['parcela'], 'total_parcelas' => $r['total_parcelas'], 'fonte' => $fonte,
            ];
        }

        $total = static function (array $itens): array {
            $compras = $creditos = 0.0;
            foreach ($itens as $i) { if ($i['tipo'] === 'DEBIT') $compras += $i['valor']; else $creditos += $i['valor']; }
            return ['compras' => round($compras, 2), 'creditos' => round($creditos, 2), 'liquido' => round($compras - $creditos, 2)];
        };

        $saldo = $conta['fatura_atual'] !== null ? (float)$conta['fatura_atual'] : abs((float)$conta['saldo']);
        $totais = ['anterior' => $total($ciclos['anterior']), 'atual' => $total($ciclos['atual']), 'proxima' => $total($ciclos['proxima'])];

        return $cache[$chave] = [
            'cartao'      => $conta['nome_marketing'] ?: trim(($conta['banco'] ?? '') . ' ' . ($conta['nome'] ?? '')),
            'dia_fechamento' => $dia,
            'saldo'       => $saldo,
            'fechamentos' => ['atual' => $C0->format('Y-m-d'), 'anterior' => $Cm1->format('Y-m-d'), 'anterior2' => $Cm2->format('Y-m-d')],
            'vencimentos' => array_map(static fn($d) => $d->format('Y-m-d'), $dues),
            'fechada'     => $C0 <= $hoje,
            'itens'       => $ciclos,
            'totais'      => $totais,
            'faturas'     => $faturaPorId,
        ];
    }

    /**
     * Itens de um cartão em uma fatura ('atual' = a que vence primeiro, 'proxima', 'anterior').
     * 'aberta' é aceito como 'proxima'. Sem faturas sincronizadas, usa o agrupamento antigo da Pierre.
     */
    public function getItensFatura(string $pierreContaId, string $periodo = 'atual'): ?array
    {
        $periodo = $periodo === 'aberta' ? 'proxima' : (in_array($periodo, ['atual', 'proxima', 'anterior'], true) ? $periodo : 'atual');
        $c = $this->ciclosDoCartao($pierreContaId);
        if ($c === null) {
            return $this->itensFaturaPorEtiqueta($pierreContaId, $periodo === 'proxima' ? 'aberta' : $periodo);
        }

        $itens = $c['itens'][$periodo];
        $t = $c['totais'][$periodo];
        $porCategoria = [];
        foreach ($itens as $i) {
            if ($i['tipo'] === 'DEBIT') {
                $cat = $i['categoria'] ?: 'Sem categoria';
                $porCategoria[$cat] = ($porCategoria[$cat] ?? 0) + $i['valor'];
            }
        }
        arsort($porCategoria);

        // Total oficial da fatura: o do banco quando conhecido; senão o saldo do cartão menos o que já é da próxima.
        $totalFatura = null;
        $fechada = $periodo === 'proxima' ? false : ($periodo === 'anterior' ? true : $c['fechada']);
        if ($periodo === 'proxima') {
            $totalFatura = $t['liquido'];
        } elseif ($periodo === 'atual') {
            $totalFatura = max(0, round($c['saldo'] - ($c['fechada'] ? $c['totais']['proxima']['liquido'] : 0), 2));
        } else {
            foreach ($c['faturas'] as $f) {
                if (abs((int)(new DateTimeImmutable($f['vencimento']))->diff(new DateTimeImmutable($c['vencimentos']['anterior']))->format('%r%a')) <= 4) $totalFatura = (float)$f['total'];
            }
        }

        $fechamento = $periodo === 'proxima' ? $this->diaDoMes(new DateTimeImmutable($c['fechamentos']['atual']), 1, $c['dia_fechamento'])->format('Y-m-d')
                    : ($periodo === 'atual' ? $c['fechamentos']['atual'] : $c['fechamentos']['anterior']);

        return [
            'modo'            => 'ciclo',
            'cartao'          => $c['cartao'],
            'periodo'         => $periodo,
            'dia_fechamento'  => $c['dia_fechamento'],
            'fechamento'      => $fechamento,
            'vencimento'      => $c['vencimentos'][$periodo],
            'fechada'         => $fechada,
            'saldo_cartao'    => $c['saldo'],
            'resumo_cartao'   => [
                'saldo'   => $c['saldo'],
                'atual'   => max(0, round($c['saldo'] - ($c['fechada'] ? $c['totais']['proxima']['liquido'] : 0), 2)),
                'proxima' => $c['totais']['proxima']['liquido'],
                'vencimento_atual'   => $c['vencimentos']['atual'],
                'vencimento_proxima' => $c['vencimentos']['proxima'],
                'fechada' => $c['fechada'],
            ],
            'total_fatura'    => $totalFatura,
            'nao_detalhado'   => $totalFatura !== null ? round(max(0, $totalFatura - $t['liquido']), 2) : null,
            'total_compras'   => $t['compras'],
            'total_creditos'  => $t['creditos'],
            'total_liquido'   => $t['liquido'],
            'por_categoria'   => array_map(
                static fn($nome, $valor) => ['categoria' => $nome, 'valor' => round($valor, 2)],
                array_keys($porCategoria), array_values($porCategoria)
            ),
            'itens'           => $itens,
        ];
    }

    /**
     * Faturas dos cartões como despesas previstas: a fatura que vence primeiro (fechada, a pagar) e,
     * se já houver compras depois do fechamento, a próxima (parcial até fechar).
     */
    public function getFaturasComoDespesasPrevistas(?string $mesReferencia = null): array
    {
        $mes = $mesReferencia !== null && trim($mesReferencia) !== ''
            ? $this->normalizarMesReferencia($mesReferencia)
            : date('Y-m');
        if ($mes === null) return [];

        $cartoes = $this->pdo->prepare("SELECT pierre_id FROM pierre_contas WHERE user_id = :u AND tipo = 'CREDIT' AND ativo = TRUE");
        $cartoes->execute([':u' => $this->userId]);

        $out = [];
        $semFaturas = false;
        foreach ($cartoes->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $c = $this->ciclosDoCartao($pid);
            if ($c === null) { $semFaturas = true; continue; }

            $atual = max(0, round($c['saldo'] - ($c['fechada'] ? $c['totais']['proxima']['liquido'] : 0), 2));
            $linhas = [
                ['venc' => $c['vencimentos']['atual'],   'valor' => $atual,                                'rotulo' => ''],
                ['venc' => $c['vencimentos']['proxima'], 'valor' => $c['totais']['proxima']['liquido'],    'rotulo' => ' (parcial, ainda abre)'],
            ];
            foreach ($linhas as $l) {
                if ($l['valor'] < 0.5 || substr($l['venc'], 0, 7) !== $mes) continue;
                $out[] = [
                    'id'                    => 'fatura:' . $pid . ':' . $l['venc'],
                    'descricao'             => 'Fatura ' . $c['cartao'] . $l['rotulo'],
                    'primeira_cobranca'     => $l['venc'],
                    'duracao_meses'         => 1,
                    'valor_parcela'         => (float)$l['valor'],
                    'ativa'                 => true,
                    'ultima_cobranca'       => $l['venc'],
                    'encerramento_previsto' => $l['venc'],
                    'origem'                => 'fatura',
                    'somente_leitura'       => true,
                ];
            }
        }

        // Cartões ainda sem faturas sincronizadas: mantém o cálculo antigo (saldo no mês do vencimento).
        if ($semFaturas) {
            $ids = array_map(static fn($r) => $r['id'], $out);
            foreach ($this->faturasPorSaldoComoDespesas($mes) as $f) {
                $pidF = substr($f['id'], 7);
                if (!$this->cartaoTemFaturas($pidF)) $out[] = $f;
            }
        }
        return $out;
    }

    private function cartaoTemFaturas(string $pierreContaId): bool
    {
        return $this->ciclosDoCartao($pierreContaId) !== null;
    }

    /**
     * Fallback: fatura = saldo do cartão no mês do vencimento (usado sem faturas sincronizadas).
     * Uma fatura entra no mês do seu vencimento; sem vencimento informado, no mês corrente.
     * Evita lançar manualmente em despesas previstas o que a API já retorna.
     */
    private function faturasPorSaldoComoDespesas(?string $mesReferencia = null): array
    {
        $mes = $mesReferencia !== null && trim($mesReferencia) !== ''
            ? $this->normalizarMesReferencia($mesReferencia)
            : date('Y-m');
        if ($mes === null) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT
                pierre_id,
                COALESCE(nome_marketing, nome, 'Cartão') AS nome,
                COALESCE(fatura_atual, ABS(saldo)) AS valor,
                vencimento,
                fechamento
            FROM pierre_contas
            WHERE user_id = :user_id
              AND tipo = 'CREDIT'
              AND COALESCE(fatura_atual, ABS(saldo)) > 0
              AND TO_CHAR(COALESCE(vencimento, CURRENT_DATE), 'YYYY-MM') = :mes
            ORDER BY vencimento ASC NULLS LAST
        ");
        $stmt->execute([':user_id' => $this->userId, ':mes' => $mes]);

        return array_map(static function (array $r) use ($mes): array {
            $venc = $r['vencimento'] ?? ($mes . '-01');
            return [
                'id'                    => 'fatura:' . $r['pierre_id'],
                'descricao'             => 'Fatura ' . $r['nome'],
                'primeira_cobranca'     => $venc,
                'duracao_meses'         => 1,
                'valor_parcela'         => (float)$r['valor'],
                'ativa'                 => true,
                'ultima_cobranca'       => $venc,
                'encerramento_previsto' => $venc,
                'origem'                => 'fatura',
                'somente_leitura'       => true,
            ];
        }, $stmt->fetchAll());
    }

    /**
     * Itens de um cartão de crédito em uma fatura.
     *
     * A Pierre informa em cada lançamento o mês da fatura (credit_card_data.billForecastDate),
     * então agrupamos por ele: 'aberta' = mês do vencimento atual; 'anterior' = mês anterior.
     * A data de fechamento (balanceCloseDate) costuma vir vazia, por isso não é usada.
     * Pagamentos da fatura (créditos "pagamento…") não são itens da fatura e ficam de fora.
     */
    private function itensFaturaPorEtiqueta(string $pierreContaId, string $periodo = 'aberta'): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pierre_id, nome, nome_marketing, banco, fatura_atual, saldo, vencimento
             FROM pierre_contas
             WHERE user_id = :u AND pierre_id = :pid AND tipo = 'CREDIT'
             LIMIT 1"
        );
        $stmt->execute([':u' => $this->userId, ':pid' => $pierreContaId]);
        $conta = $stmt->fetch();
        if (!$conta) {
            return null;
        }

        $base = !empty($conta['vencimento'])
            ? new DateTimeImmutable(substr((string)$conta['vencimento'], 0, 7) . '-01')
            : new DateTimeImmutable('first day of this month');
        $anterior = $periodo === 'anterior';
        $mesFatura = ($anterior ? $base->modify('-1 month') : $base)->format('Y-m');

        // Fatura aberta também inclui encargos/lançamentos ainda PENDENTES de meses anteriores
        // (ex.: juros e multa do rotativo), que o banco só fecha na fatura seguinte.
        $mesAnterior = (new DateTimeImmutable($mesFatura . '-01'))->modify('-1 month')->format('Y-m');
        $pendentes = $anterior ? '' : " OR (status = 'PENDING' AND dados_raw->'credit_card_data'->>'billForecastDate' = :mes_ant)";

        $tx = $this->pdo->prepare(
            "SELECT pierre_id, data, descricao, valor, tipo, categoria,
                    dados_raw->'credit_card_data'->>'installmentNumber' AS parcela,
                    dados_raw->'credit_card_data'->>'totalInstallments' AS total_parcelas,
                    (dados_raw->'credit_card_data'->>'billForecastDate' <> :mes_sel) AS de_mes_anterior
             FROM pierre_transacoes
             WHERE user_id = :u
               AND pierre_conta_id = :pid
               AND (dados_raw->'credit_card_data'->>'billForecastDate' = :mes{$pendentes})
               AND NOT (tipo = 'CREDIT' AND LOWER(descricao) LIKE '%pagamento%')
             ORDER BY " . self::ORDEM_RECENTE
        );
        $params = [':u' => $this->userId, ':pid' => $pierreContaId, ':mes' => $mesFatura, ':mes_sel' => $mesFatura];
        if (!$anterior) $params[':mes_ant'] = $mesAnterior;
        $tx->execute($params);
        $itens = $tx->fetchAll();

        $compras = 0.0;
        $creditos = 0.0;
        $porCategoria = [];
        foreach ($itens as &$it) {
            $it['valor'] = abs((float)$it['valor']);
            if ($it['tipo'] === 'DEBIT') {
                $compras += $it['valor'];
                $cat = $it['categoria'] ?: 'Sem categoria';
                $porCategoria[$cat] = ($porCategoria[$cat] ?? 0) + $it['valor'];
            } else {
                $creditos += $it['valor'];
            }
        }
        unset($it);
        arsort($porCategoria);

        $totalApi = $conta['fatura_atual'] !== null ? (float)$conta['fatura_atual'] : abs((float)$conta['saldo']);
        $liquido = round($compras - $creditos, 2);

        return [
            'cartao'          => $conta['nome_marketing'] ?: trim(($conta['banco'] ?? '') . ' ' . ($conta['nome'] ?? '')),
            'periodo'         => $anterior ? 'anterior' : 'aberta',
            'mes_fatura'      => $mesFatura,
            'vencimento'      => $anterior ? null : $conta['vencimento'],
            // Total oficial só existe para a fatura aberta; a diferença para os itens são
            // lançamentos que o banco ainda não detalhou.
            'fatura_atual'    => $anterior ? null : $totalApi,
            'nao_detalhado'   => $anterior ? null : round(max(0, $totalApi - $liquido), 2),
            'total_compras'   => round($compras, 2),
            'total_creditos'  => round($creditos, 2),
            'total_liquido'   => $liquido,
            'por_categoria'   => array_map(
                static fn($nome, $valor) => ['categoria' => $nome, 'valor' => round($valor, 2)],
                array_keys($porCategoria), array_values($porCategoria)
            ),
            'itens'           => $itens,
        ];
    }

    /**
     * Assinaturas (detectadas + manuais) como despesas previstas de um mês (YYYY-MM).
     * Assinatura cobrada no cartão de crédito já está dentro da fatura: vem marcada com
     * incluida_na_fatura para aparecer na lista sem ser somada duas vezes.
     */
    public function getAssinaturasComoDespesasPrevistas(?string $mesReferencia = null): array
    {
        $mes = $mesReferencia !== null && trim($mesReferencia) !== ''
            ? $this->normalizarMesReferencia($mesReferencia)
            : date('Y-m');
        if ($mes === null) return [];

        $alvo = new DateTimeImmutable($mes . '-01');
        $diff = static fn(DateTimeImmutable $a, DateTimeImmutable $b): int =>
            ((int)$b->format('Y') - (int)$a->format('Y')) * 12 + ((int)$b->format('n') - (int)$a->format('n'));

        $out = [];
        foreach ($this->getAssinaturas(true) as $a) {
            $proxima = !empty($a['proxima_cobranca']) ? new DateTimeImmutable(substr((string)$a['proxima_cobranca'], 0, 10)) : null;
            if ($proxima === null) continue;
            $base = new DateTimeImmutable($proxima->format('Y-m-01'));
            $d = $diff($base, $alvo);

            switch ($a['periodicidade']) {
                case 'MONTHLY':   $entra = $d >= -1;                         break;  // vale do mês anterior ao da próxima cobrança em diante
                case 'QUARTERLY': $entra = $d % 3 === 0;                     break;
                case 'YEARLY':    $entra = $d % 12 === 0;                    break;
                default:          $entra = $d >= -1;
            }
            if (!$entra) continue;

            // Assinatura no cartão já está dentro dos itens da fatura: não entra em despesas previstas.
            if (($a['conta_tipo'] ?? '') === 'CREDIT') continue;

            $dia = min((int)$proxima->format('j'), (int)$alvo->format('t'));
            $out[] = [
                'id'                    => 'as:' . $a['id'],
                'descricao'             => $a['descricao'],
                'primeira_cobranca'     => $alvo->format('Y-m-') . sprintf('%02d', $dia),
                'duracao_meses'         => null,
                'valor_parcela'         => (float)$a['valor'],
                'ativa'                 => true,
                'encerramento_previsto' => null,
                'origem'                => 'assinatura',
                'banco'                 => $a['banco'] ?? null,
                'incluida_na_fatura'    => ($a['conta_tipo'] ?? '') === 'CREDIT',
                'somente_leitura'       => true,
                'assinatura_origem'     => 'detectada',
                'assinatura_id'         => $a['id'],
                'valor_assinatura'      => (float)$a['valor'],
                'logo_url'              => logoDaMarca($a['descricao']),
            ];
        }

        foreach ($this->getAssinaturasManuais(true) as $a) {
            $ini = !empty($a['ultima_cobranca']) ? new DateTimeImmutable(substr((string)$a['ultima_cobranca'], 0, 7) . '-01')
                                                 : new DateTimeImmutable(substr((string)$a['proxima_cobranca'], 0, 7) . '-01');
            if ($diff($ini, $alvo) < 0) continue;
            if (($a['conta_tipo'] ?? '') === 'CREDIT') continue;   // já está dentro da fatura do cartão
            $out[] = [
                'id'                    => 'am:' . $a['id'],
                'descricao'             => $a['descricao'],
                'primeira_cobranca'     => $alvo->format('Y-m-01'),
                'duracao_meses'         => null,
                'valor_parcela'         => (float)$a['valor'],
                'ativa'                 => true,
                'encerramento_previsto' => null,
                'origem'                => 'assinatura',
                'banco'                 => $a['banco'] ?? null,
                'incluida_na_fatura'    => ($a['conta_tipo'] ?? '') === 'CREDIT',
                'somente_leitura'       => true,
                'assinatura_origem'     => 'manual',
                'assinatura_id'         => $a['id'],
                'valor_assinatura'      => (float)$a['valor'],
                'logo_url'              => logoDaMarca($a['descricao']),
            ];
        }

        return $out;
    }

    public function getAssinaturasManuais(bool $somenteAtivas = true): array
    {
        $this->ensureDespesasPrevistasTable();

        $sql = "
            SELECT
                id,
                descricao,
                COALESCE(valor_mensal, 0) AS valor,
                'MONTHLY' AS periodicidade,
                CASE
                    WHEN primeira_cobranca > CURRENT_DATE THEN NULL
                    ELSE DATE_TRUNC('month', CURRENT_DATE)::date
                END AS ultima_cobranca,
                CASE
                    WHEN primeira_cobranca > CURRENT_DATE THEN primeira_cobranca
                    ELSE (DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month')::date
                END AS proxima_cobranca,
                NULL::text AS categoria,
                COALESCE(banco, 'Manual')::text AS conta_nome,
                banco,
                conta_tipo,
                ativa,
                criado_em,
                'manual'::text AS origem
            FROM pierre_despesas_previstas
                        WHERE user_id = :user_id
                            AND duracao_meses IS NULL
        ";

        if ($somenteAtivas) {
            $sql .= ' AND ativa = TRUE';
        }

        $sql .= ' ORDER BY criado_em DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $this->userId]);
        return $stmt->fetchAll();
    }

    /**
     * Retorna assinaturas (por padrão somente ativas).
     */
    public function getAssinaturas(bool $apenasAtivas = true): array
    {
        // Uma única vez após a nova lógica de detecção: descarta as assinaturas antigas (muitas erradas).
        try {
            schemaOnce($this->pdo, 'pierre_assinaturas_deteccao_v2', fn() => $this->detectarAssinaturas());
        } catch (\Throwable $e) {
            // sem transações ainda: segue com o que existir
        }

        $sql = 'SELECT * FROM pierre_assinaturas WHERE user_id = :user_id';
        if ($apenasAtivas) $sql .= ' AND ativa = TRUE';
        $sql .= ' ORDER BY proxima_cobranca ASC NULLS LAST, valor DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $this->userId]);
        return $stmt->fetchAll();
    }

    /* ════════════════════════════════════════════
       LOG DE SINCRONIZAÇÃO
    ════════════════════════════════════════════ */

    public function registrarSincronizacao(array $dados): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pierre_sincronizacoes
                (user_id, tipo, status, total_contas, total_transacoes, total_assinaturas, detalhes)
            VALUES
                (:user_id, :tipo, :status, :total_contas, :total_transacoes, :total_assinaturas, :detalhes)
        ");
        $stmt->execute([
            ':user_id'           => $this->userId,
            ':tipo'              => $dados['tipo']              ?? 'completo',
            ':status'            => $dados['status']            ?? 'sucesso',
            ':total_contas'      => $dados['total_contas']      ?? 0,
            ':total_transacoes'  => $dados['total_transacoes']  ?? 0,
            ':total_assinaturas' => $dados['total_assinaturas'] ?? 0,
            ':detalhes'          => $dados['detalhes']          ?? null,
        ]);
    }

    public function getUltimaSincronizacao(): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pierre_sincronizacoes WHERE user_id = :user_id ORDER BY sincronizado_em DESC LIMIT 1'
        );
        $stmt->execute([':user_id' => $this->userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /* ════════════════════════════════════════════
       CATEGORIAS
    ════════════════════════════════════════════ */

    /**
     * Lista categorias já existentes nas transações + categorias manuais.
     */
    public function getCategoriasDisponiveis(): array
    {
        $this->ensureCategoriasTable();

        $categorias = [];

                $stmtTx = $this->pdo->prepare(
            "SELECT DISTINCT TRIM(categoria) AS nome
             FROM pierre_transacoes
                         WHERE user_id = :user_id
                             AND categoria IS NOT NULL
                             AND TRIM(categoria) <> ''"
        );
                $stmtTx->execute([':user_id' => $this->userId]);

        foreach ($stmtTx->fetchAll() as $row) {
            $categorias[] = $row['nome'];
        }

        $stmtCustom = $this->pdo->prepare(
            'SELECT nome FROM pierre_categorias_custom WHERE user_id = :user_id ORDER BY nome'
        );
        $stmtCustom->execute([':user_id' => $this->userId]);

        foreach ($stmtCustom->fetchAll() as $row) {
            $categorias[] = $row['nome'];
        }

        $categorias = array_values(array_unique(array_filter($categorias)));
        natcasesort($categorias);

        return array_values($categorias);
    }

    /**
     * Lista somente categorias cadastradas manualmente pelo usuário.
     */
    public function getCategoriasCadastradas(): array
    {
        $this->ensureCategoriasTable();

        $stmt = $this->pdo->prepare(
            'SELECT nome FROM pierre_categorias_custom WHERE user_id = :user_id ORDER BY nome'
        );
        $stmt->execute([':user_id' => $this->userId]);

        return array_map(
            fn($row) => $row['nome'],
            $stmt->fetchAll()
        );
    }

    /**
     * Cria uma categoria personalizada para ficar disponível no seletor.
     */
    public function criarCategoria(string $nome): bool
    {
        $this->ensureCategoriasTable();

        $nome = trim($nome);
        if ($nome === '') return false;

        $stmt = $this->pdo->prepare(
            'INSERT INTO pierre_categorias_custom (user_id, nome)
             SELECT :user_id, :nome
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM pierre_categorias_custom
                 WHERE user_id = :user_id
                   AND LOWER(TRIM(nome)) = LOWER(TRIM(:nome))
             )'
        );

        return $stmt->execute([':user_id' => $this->userId, ':nome' => $nome]);
    }

    /**
     * Atualiza a categoria de uma transação pelo ID da Pierre.
     */
    public function atualizarCategoriaTransacao(string $pierreId, string $categoria): bool
    {
        $categoria = trim($categoria);
        if ($pierreId === '' || $categoria === '') return false;

        $sql = "
            UPDATE pierre_transacoes
            SET
                categoria = :categoria,
                dados_raw = jsonb_set(
                    COALESCE(dados_raw, '{}'::jsonb),
                    '{category}',
                    to_jsonb(CAST(:categoria AS text)),
                    true
                )
            WHERE pierre_id = :pierre_id
                            AND user_id = :user_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':categoria' => $categoria,
            ':pierre_id' => $pierreId,
            ':user_id'   => $this->userId,
        ]);

        if ($ok) {
            $this->criarCategoria($categoria);
        }

        return $ok && $stmt->rowCount() > 0;
    }

    /**
     * Renomeia categoria em transações e mantém cadastro custom sincronizado.
     */
    public function renomearCategoria(string $categoriaAntiga, string $categoriaNova): bool
    {
        $categoriaAntiga = trim($categoriaAntiga);
        $categoriaNova   = trim($categoriaNova);

        if ($categoriaAntiga === '' || $categoriaNova === '') return false;

        $sql = "
            UPDATE pierre_transacoes
            SET
                categoria = :nova,
                dados_raw = jsonb_set(
                    COALESCE(dados_raw, '{}'::jsonb),
                    '{category}',
                    to_jsonb(CAST(:nova AS text)),
                    true
                )
            WHERE LOWER(TRIM(categoria)) = LOWER(TRIM(:antiga))
                            AND user_id = :user_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nova'   => $categoriaNova,
            ':antiga' => $categoriaAntiga,
            ':user_id' => $this->userId,
        ]);

        $this->criarCategoria($categoriaNova);
        $this->ensureCategoriasTable();

        $del = $this->pdo->prepare(
            'DELETE FROM pierre_categorias_custom
             WHERE user_id = :user_id AND LOWER(TRIM(nome)) = LOWER(TRIM(:nome))'
        );
        $del->execute([':user_id' => $this->userId, ':nome' => $categoriaAntiga]);

        return true;
    }

    /* ────────────────────────────────────────────
       Helpers privados
    ──────────────────────────────────────────── */

    private function parseDate(?string $val): ?string
    {
        if (!$val) return null;
        // Data pura (ex.: vencimento) não tem fuso: mantém como veio.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;
        try {
            // A API devolve instantes em UTC (…Z): converte para o horário de Brasília,
            // senão compras feitas à noite caem no dia seguinte.
            return (new DateTime($val))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function ensureCategoriasTable(): void
    {
        schemaOnce($this->pdo, 'pierre_categorias_v1', fn() => $this->runEnsureCategoriasTable());
    }

    private function runEnsureCategoriasTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS pierre_categorias_custom (
                id SERIAL PRIMARY KEY,
                user_id UUID NOT NULL,
                nome VARCHAR(150) NOT NULL,
                criado_em TIMESTAMPTZ DEFAULT NOW()
            )"
        );

        $this->pdo->exec('ALTER TABLE pierre_categorias_custom ADD COLUMN IF NOT EXISTS user_id UUID');
        $this->pdo->exec('DROP INDEX IF EXISTS idx_categorias_custom_nome_unique');
        $this->pdo->exec('ALTER TABLE pierre_categorias_custom DROP CONSTRAINT IF EXISTS pierre_categorias_custom_nome_key');
        $this->pdo->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS idx_categorias_custom_nome_unique
             ON pierre_categorias_custom (user_id, LOWER(nome))'
        );
    }

    private function ensureAssinaturasTable(): void
    {
        schemaOnce($this->pdo, 'pierre_assinaturas_v1', fn() => $this->runEnsureAssinaturasTable());
        schemaOnce($this->pdo, 'pierre_assinaturas_cols_v1', fn() => $this->pdo->exec(
            'ALTER TABLE pierre_assinaturas ADD COLUMN IF NOT EXISTS banco VARCHAR(150), ADD COLUMN IF NOT EXISTS icone_url TEXT'
        ));
    }

    private function runEnsureAssinaturasTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS pierre_assinaturas (
                id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
                user_id UUID NOT NULL,
                descricao TEXT NOT NULL,
                valor NUMERIC(15,2) NOT NULL,
                periodicidade VARCHAR(20) DEFAULT 'MONTHLY',
                ultima_cobranca DATE,
                proxima_cobranca DATE,
                categoria VARCHAR(150),
                conta_nome VARCHAR(250),
                conta_tipo VARCHAR(30),
                ativa BOOLEAN DEFAULT TRUE,
                criado_em TIMESTAMPTZ DEFAULT NOW(),
                atualizado_em TIMESTAMPTZ DEFAULT NOW()
            )"
        );

        $this->pdo->exec('ALTER TABLE pierre_assinaturas ADD COLUMN IF NOT EXISTS user_id UUID');

        // Remove duplicatas legadas para permitir criar/usar o índice único do upsert.
        $this->pdo->exec(
            "WITH ranked AS (
                SELECT
                    id,
                    ROW_NUMBER() OVER (
                        PARTITION BY user_id, LOWER(TRIM(descricao)), ROUND(valor::numeric, 2)
                        ORDER BY atualizado_em DESC NULLS LAST, criado_em DESC NULLS LAST, id DESC
                    ) AS rn
                FROM pierre_assinaturas
            )
            DELETE FROM pierre_assinaturas a
            USING ranked r
            WHERE a.id = r.id
              AND r.rn > 1"
        );

        $this->pdo->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS idx_pierre_assin_uniq
             ON pierre_assinaturas (user_id, LOWER(descricao), valor)'
        );
    }

    private function ensureAssinaturasIgnoradasTable(): void
    {
        schemaOnce($this->pdo, 'pierre_assinaturas_ignoradas_v1', fn() => $this->runEnsureAssinaturasIgnoradasTable());
    }

    private function runEnsureAssinaturasIgnoradasTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS pierre_assinaturas_ignoradas (
                id SERIAL PRIMARY KEY,
                user_id UUID NOT NULL,
                descricao TEXT NOT NULL,
                valor NUMERIC(15,2) NOT NULL,
                criado_em TIMESTAMPTZ DEFAULT NOW()
            )"
        );

        $this->pdo->exec('ALTER TABLE pierre_assinaturas_ignoradas ADD COLUMN IF NOT EXISTS user_id UUID');

        $this->pdo->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS idx_assin_ignoradas_desc_valor
             ON pierre_assinaturas_ignoradas (user_id, LOWER(descricao), valor)'
        );
    }

    private function ensureDespesasPrevistasTable(): void
    {
        schemaOnce($this->pdo, 'pierre_despesas_previstas_v1', fn() => $this->runEnsureDespesasPrevistasTable());
        schemaOnce($this->pdo, 'pierre_despesas_prev_banco_v1', fn() => $this->pdo->exec(
            'ALTER TABLE pierre_despesas_previstas ADD COLUMN IF NOT EXISTS banco TEXT, ADD COLUMN IF NOT EXISTS conta_tipo VARCHAR(30)'
        ));
    }

    private function runEnsureDespesasPrevistasTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS pierre_despesas_previstas (
                id UUID PRIMARY KEY,
                user_id UUID NOT NULL,
                descricao TEXT NOT NULL,
                primeira_cobranca DATE NOT NULL,
                duracao_meses INT,
                valor_mensal NUMERIC(15,2),
                ativa BOOLEAN DEFAULT TRUE,
                criado_em TIMESTAMPTZ DEFAULT NOW()
            )"
        );

        $this->pdo->exec('ALTER TABLE pierre_despesas_previstas ADD COLUMN IF NOT EXISTS user_id UUID');

        $this->pdo->exec('ALTER TABLE pierre_despesas_previstas ALTER COLUMN duracao_meses DROP NOT NULL');
        $this->pdo->exec('ALTER TABLE pierre_despesas_previstas ADD COLUMN IF NOT EXISTS valor_mensal NUMERIC(15,2)');

        $this->pdo->exec(
            "DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'pierre_despesas_previstas_duracao_check'
                ) THEN
                    ALTER TABLE pierre_despesas_previstas
                    ADD CONSTRAINT pierre_despesas_previstas_duracao_check
                    CHECK (duracao_meses IS NULL OR duracao_meses > 0);
                END IF;
            END $$;"
        );
    }

    private function ensureUserScopedSchema(): void
    {
        $tables = [
            'pierre_contas',
            'pierre_transacoes',
            'pierre_sincronizacoes',
            'pierre_assinaturas',
            'pierre_assinaturas_ignoradas',
            'pierre_despesas_previstas',
            'pierre_categorias_custom',
        ];

        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $this->pdo->exec("ALTER TABLE IF EXISTS {$table} ADD COLUMN IF NOT EXISTS user_id UUID");
            }
        }

        $this->adoptLegacyRowsWithoutUser();
        $this->deduplicateUserScopedRows();

        if ($this->tableExists('pierre_contas')) {
            $this->pdo->exec('DROP INDEX IF EXISTS idx_pierre_contas_pierre_id');
            $this->pdo->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS idx_pierre_contas_user_pierre_id
                 ON pierre_contas (user_id, pierre_id)'
            );
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_pierre_contas_user ON pierre_contas (user_id)');
        }

        if ($this->tableExists('pierre_transacoes')) {
            $this->pdo->exec('ALTER TABLE pierre_transacoes DROP CONSTRAINT IF EXISTS pierre_transacoes_pierre_id_key');
            $this->pdo->exec('DROP INDEX IF EXISTS idx_pierre_tx_user_pierre_id');
            $this->pdo->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS idx_pierre_tx_user_pierre_id
                 ON pierre_transacoes (user_id, pierre_id)
                 WHERE pierre_id IS NOT NULL'
            );
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_pierre_transacoes_user ON pierre_transacoes (user_id)');
        }

        if ($this->tableExists('pierre_sincronizacoes')) {
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_pierre_sincronizacoes_user ON pierre_sincronizacoes (user_id)');
        }

        if ($this->tableExists('pierre_assinaturas')) {
            $this->pdo->exec('DROP INDEX IF EXISTS idx_pierre_assin_uniq');
            $this->pdo->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS idx_pierre_assin_uniq
                 ON pierre_assinaturas (user_id, LOWER(descricao), valor)'
            );
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_pierre_assinaturas_user ON pierre_assinaturas (user_id)');
        }

        if ($this->tableExists('pierre_assinaturas_ignoradas')) {
            $this->pdo->exec('DROP INDEX IF EXISTS idx_assin_ignoradas_desc_valor');
            $this->pdo->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS idx_assin_ignoradas_desc_valor
                 ON pierre_assinaturas_ignoradas (user_id, LOWER(descricao), valor)'
            );
        }

        if ($this->tableExists('pierre_despesas_previstas')) {
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_despesas_previstas_user ON pierre_despesas_previstas (user_id)');
        }

        if ($this->tableExists('pierre_categorias_custom')) {
            $this->pdo->exec('ALTER TABLE pierre_categorias_custom DROP CONSTRAINT IF EXISTS pierre_categorias_custom_nome_key');
            $this->pdo->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS idx_categorias_custom_nome_unique
                 ON pierre_categorias_custom (user_id, LOWER(nome))'
            );
        }

        $this->ensureUserForeignKeys();
    }

    private function deduplicateUserScopedRows(): void
    {
        if ($this->tableExists('pierre_contas')) {
            $this->pdo->exec(
                "WITH ranked AS (
                    SELECT id,
                           ROW_NUMBER() OVER (
                               PARTITION BY user_id, pierre_id
                               ORDER BY atualizado_em DESC NULLS LAST, sincronizado_em DESC NULLS LAST, criado_em DESC NULLS LAST, id DESC
                           ) AS rn
                    FROM pierre_contas
                )
                DELETE FROM pierre_contas c
                USING ranked r
                WHERE c.id = r.id
                  AND r.rn > 1"
            );
        }

        if ($this->tableExists('pierre_transacoes')) {
            $this->pdo->exec(
                "WITH ranked AS (
                    SELECT id,
                           ROW_NUMBER() OVER (
                               PARTITION BY user_id, pierre_id
                               ORDER BY sincronizado_em DESC NULLS LAST, criado_em DESC NULLS LAST, id DESC
                           ) AS rn
                    FROM pierre_transacoes
                    WHERE pierre_id IS NOT NULL
                )
                DELETE FROM pierre_transacoes t
                USING ranked r
                WHERE t.id = r.id
                  AND r.rn > 1"
            );
        }

        if ($this->tableExists('pierre_categorias_custom')) {
            $this->pdo->exec(
                "WITH ranked AS (
                    SELECT id,
                           ROW_NUMBER() OVER (
                               PARTITION BY user_id, LOWER(TRIM(nome))
                               ORDER BY criado_em DESC NULLS LAST, id DESC
                           ) AS rn
                    FROM pierre_categorias_custom
                )
                DELETE FROM pierre_categorias_custom c
                USING ranked r
                WHERE c.id = r.id
                  AND r.rn > 1"
            );
        }

        if ($this->tableExists('pierre_assinaturas')) {
            $this->pdo->exec(
                "WITH ranked AS (
                    SELECT id,
                           ROW_NUMBER() OVER (
                               PARTITION BY user_id, LOWER(TRIM(descricao)), ROUND(valor::numeric, 2)
                               ORDER BY atualizado_em DESC NULLS LAST, criado_em DESC NULLS LAST, id DESC
                           ) AS rn
                    FROM pierre_assinaturas
                )
                DELETE FROM pierre_assinaturas a
                USING ranked r
                WHERE a.id = r.id
                  AND r.rn > 1"
            );
        }

        if ($this->tableExists('pierre_assinaturas_ignoradas')) {
            $this->pdo->exec(
                "WITH ranked AS (
                    SELECT id,
                           ROW_NUMBER() OVER (
                               PARTITION BY user_id, LOWER(TRIM(descricao)), ROUND(valor::numeric, 2)
                               ORDER BY criado_em DESC NULLS LAST, id DESC
                           ) AS rn
                    FROM pierre_assinaturas_ignoradas
                )
                DELETE FROM pierre_assinaturas_ignoradas i
                USING ranked r
                WHERE i.id = r.id
                  AND r.rn > 1"
            );
        }
    }

    private function adoptLegacyRowsWithoutUser(): void
    {
        // Migração compatível: se ainda existem registros legados sem user_id,
        // assume que pertencem ao usuário atualmente autenticado.
        $tables = [
            'pierre_contas',
            'pierre_transacoes',
            'pierre_categorias_custom',
            'pierre_despesas_previstas',
            'pierre_assinaturas',
            'pierre_assinaturas_ignoradas',
            'pierre_sincronizacoes',
        ];

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $stmt = $this->pdo->prepare(
                "UPDATE {$table}
                 SET user_id = :user_id
                 WHERE user_id IS NULL"
            );
            $stmt->execute([':user_id' => $this->userId]);
        }
    }

    private function ensureUserForeignKeys(): void
    {
        $fks = [
            'pierre_contas' => 'fk_pierre_contas_user',
            'pierre_transacoes' => 'fk_pierre_transacoes_user',
            'pierre_categorias_custom' => 'fk_pierre_categorias_user',
            'pierre_despesas_previstas' => 'fk_pierre_despesas_user',
            'pierre_assinaturas' => 'fk_pierre_assinaturas_user',
            'pierre_assinaturas_ignoradas' => 'fk_pierre_assin_ign_user',
            'pierre_sincronizacoes' => 'fk_pierre_sync_user',
        ];

        foreach ($fks as $table => $fkName) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $this->pdo->exec(
                "DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM pg_constraint
                        WHERE conname = '{$fkName}'
                    ) THEN
                        ALTER TABLE {$table}
                        ADD CONSTRAINT {$fkName}
                        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE;
                    END IF;
                END $$;"
            );
        }
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare("SELECT to_regclass(:table_name) IS NOT NULL");
        $stmt->execute([':table_name' => 'public.' . $tableName]);
        return (bool)$stmt->fetchColumn();
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function normalizarMesReferencia(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') return null;

        // yyyy-mm
        if (preg_match('/^(\d{4})-(\d{2})$/', $valor, $m)) {
            $ano = (int)$m[1];
            $mes = (int)$m[2];
            return ($mes >= 1 && $mes <= 12) ? sprintf('%04d-%02d', $ano, $mes) : null;
        }

        // yyyy-mm-dd
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m)) {
            $ano = (int)$m[1];
            $mes = (int)$m[2];
            return ($mes >= 1 && $mes <= 12) ? sprintf('%04d-%02d', $ano, $mes) : null;
        }

        // mm/yyyy
        if (preg_match('/^(\d{2})\/(\d{4})$/', $valor, $m)) {
            $mes = (int)$m[1];
            $ano = (int)$m[2];
            return ($mes >= 1 && $mes <= 12) ? sprintf('%04d-%02d', $ano, $mes) : null;
        }

        return null;
    }
}
