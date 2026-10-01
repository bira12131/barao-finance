<?php
/**
 * REPOSITORY — PierreRepository
 *
 * CRUD para as tabelas pierre_* no PostgreSQL.
 * Usado pelo PierreService para persistir e recuperar dados da API Pierre Finance.
 */

require_once __DIR__ . '/../config/database.php';

class PierreRepository
{
    private PDO $pdo;
    private string $userId;

    public function __construct(string $userId)
    {
        $this->userId = trim($userId);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $this->userId)) {
            throw new InvalidArgumentException('userId inválido para PierreRepository.');
        }

        $this->pdo = getConnection();
        $this->ensureUserScopedSchema();
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

        foreach ($contas as $c) {
            $cd      = $c['creditData'] ?? [];
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
        if (empty($transacoes)) return 0;

        $sqlUpdate = "
            UPDATE pierre_transacoes
            SET
                pierre_conta_id  = :pierre_conta_id,
                data             = :data,
                descricao        = :descricao,
                valor            = :valor,
                tipo             = :tipo,
                categoria        = :categoria,
                conta_nome       = :conta_nome,
                conta_tipo       = :conta_tipo,
                status           = :status,
                dados_raw        = :dados_raw::jsonb,
                sincronizado_em  = NOW()
            WHERE user_id = :user_id
              AND pierre_id = :pierre_id
        ";

        $sqlInsert = "
            INSERT INTO pierre_transacoes (
                user_id, pierre_id, pierre_conta_id, data, descricao, valor,
                tipo, categoria, conta_nome, conta_tipo, status,
                dados_raw, sincronizado_em
            ) VALUES (
                :user_id, :pierre_id, :pierre_conta_id, :data, :descricao, :valor,
                :tipo, :categoria, :conta_nome, :conta_tipo, :status,
                :dados_raw::jsonb, NOW()
            )
        ";

        $stmtUpdate = $this->pdo->prepare($sqlUpdate);
        $stmtInsert = $this->pdo->prepare($sqlInsert);
        $count = 0;

        foreach ($transacoes as $tx) {
            $pierreId = $tx['id'] ?? $tx['transactionId'] ?? null;
            if (!$pierreId) continue; // sem ID não há como fazer upsert confiável

            $valor = (float)($tx['amount'] ?? 0);
            $tipo  = strtoupper($tx['type'] ?? ($valor >= 0 ? 'CREDIT' : 'DEBIT'));
            $data  = $this->parseDate($tx['date'] ?? null) ?? date('Y-m-d');

            $params = [
                ':user_id'         => $this->userId,
                ':pierre_id'       => $pierreId,
                ':pierre_conta_id' => $tx['accountId']   ?? ($tx['account_id'] ?? null),
                ':data'            => $data,
                ':descricao'       => $tx['description']  ?? 'Sem descrição',
                ':valor'           => $valor,
                ':tipo'            => $tipo,
                ':categoria'       => $tx['category']     ?? null,
                ':conta_nome'      => $tx['account_name'] ?? $tx['accountName'] ?? null,
                ':conta_tipo'      => $tx['accountType']  ?? ($tx['account_type'] ?? null),
                ':status'          => $tx['status']       ?? null,
                ':dados_raw'       => json_encode($tx, JSON_UNESCAPED_UNICODE),
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
        $sql      = "SELECT * FROM pierre_transacoes {$whereStr} ORDER BY data DESC, criado_em DESC";

        if (!empty($filtros['limit'])) {
            $sql .= ' LIMIT ' . (int)$filtros['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(function ($row) {
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

    /**
     * Detecta assinaturas recorrentes nas transações armazenadas.
     *
     * Lógica: agrupa DEBIT por descrição normalizada + valor,
     * filtra grupos com ≥ 2 ocorrências nos últimos 120 dias e
     * calcula o intervalo médio para determinar a periodicidade.
     */
    public function detectarAssinaturas(): int
    {
        $this->ensureAssinaturasTable();
        $this->ensureAssinaturasIgnoradasTable();

        // Recria as assinaturas detectadas para evitar dependência de ON CONFLICT em bancos legados.
        $deleteStmt = $this->pdo->prepare('DELETE FROM pierre_assinaturas WHERE user_id = :user_id');
        $deleteStmt->execute([':user_id' => $this->userId]);

        $sql = "
            WITH recorrentes AS (
                SELECT
                    REGEXP_REPLACE(
                        REGEXP_REPLACE(LOWER(TRIM(descricao)), '[^[:alpha:] ]', ' ', 'g'),
                        '\\s+', ' ', 'g'
                    )                                             AS desc_norm,
                    MODE() WITHIN GROUP (ORDER BY descricao)      AS descricao_base,
                                        ROUND(AVG(ABS(valor))::numeric, 2)            AS valor_norm,
                    COALESCE(STDDEV_POP(ABS(valor)), 0)           AS desvio_valor,
                    COUNT(*)                                      AS ocorrencias,
                    MAX(data)                                     AS ultima,
                    MIN(data)                                     AS primeira,
                    MODE() WITHIN GROUP (ORDER BY categoria)      AS categoria,
                    MODE() WITHIN GROUP (ORDER BY conta_nome)     AS conta_nome,
                    MODE() WITHIN GROUP (ORDER BY conta_tipo)     AS conta_tipo
                FROM pierre_transacoes
                                WHERE user_id = :user_id
                                    AND tipo = 'DEBIT'
                  AND data >= NOW() - INTERVAL '400 days'
                                    AND TRIM(descricao) <> ''
                GROUP BY REGEXP_REPLACE(
                    REGEXP_REPLACE(LOWER(TRIM(descricao)), '[^[:alpha:] ]', ' ', 'g'),
                    '\\s+', ' ', 'g'
                )
                HAVING COUNT(*) >= 2
            ),
            com_intervalo AS (
                SELECT *,
                    ((ultima - primeira)::numeric)
                        / GREATEST(ocorrencias - 1, 1) AS intervalo_medio,
                    CASE
                        WHEN valor_norm > 0 THEN desvio_valor / valor_norm
                        ELSE 0
                    END AS desvio_relativo
                FROM recorrentes
            )
            INSERT INTO pierre_assinaturas (
                user_id, descricao, valor, periodicidade,
                ultima_cobranca, proxima_cobranca,
                categoria, conta_nome, conta_tipo
            )
            SELECT
                :user_id,
                descricao_base,
                valor_norm,
                CASE
                    WHEN intervalo_medio BETWEEN 6   AND 8   THEN 'WEEKLY'
                    WHEN intervalo_medio BETWEEN 25  AND 35  THEN 'MONTHLY'
                    WHEN intervalo_medio BETWEEN 80  AND 100 THEN 'QUARTERLY'
                    WHEN intervalo_medio BETWEEN 355 AND 375 THEN 'YEARLY'
                    ELSE 'MONTHLY'
                END                                                  AS periodicidade,
                ultima                                               AS ultima_cobranca,
                CASE
                    WHEN intervalo_medio BETWEEN 6   AND 8   THEN ultima +   7
                    WHEN intervalo_medio BETWEEN 25  AND 35  THEN ultima +  30
                    WHEN intervalo_medio BETWEEN 80  AND 100 THEN ultima +  90
                    ELSE                                          ultima + 365
                END                                                  AS proxima_cobranca,
                categoria,
                conta_nome,
                conta_tipo
            FROM com_intervalo
                        WHERE intervalo_medio BETWEEN 20 AND 40
                            AND desvio_relativo <= 0.35
                            AND desc_norm !~ '(pix|transfer|ted|doc|fatura|pagamento recebido|pagamento com qr|saque|deposito|dep\b|reserva|mesma titularidade|enviad|recebid)'
                            AND NOT EXISTS (
                                SELECT 1
                                FROM pierre_assinaturas_ignoradas i
                                                                WHERE i.user_id = :user_id
                                                                    AND 
                                                                            LOWER(TRIM(i.descricao)) = LOWER(TRIM(descricao_base))
                                  AND ROUND(i.valor::numeric, 2) = ROUND(valor_norm::numeric, 2)
                            )

        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $this->userId]);

        $countStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM pierre_assinaturas WHERE ativa = TRUE AND user_id = :user_id'
        );
        $countStmt->execute([':user_id' => $this->userId]);
        return (int) $countStmt->fetchColumn();
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

    public function criarAssinaturaManual(string $descricao, string $primeiraCobrancaMes, float $valorMensal): bool
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
            'INSERT INTO pierre_despesas_previstas (id, user_id, descricao, primeira_cobranca, duracao_meses, valor_mensal, ativa)
             VALUES (:id, :user_id, :descricao, :primeira_cobranca, NULL, :valor_mensal, TRUE)'
        );

        return $stmt->execute([
            ':id'               => $id,
            ':user_id'          => $this->userId,
            ':descricao'        => $descricao,
            ':primeira_cobranca'=> $primeiraCobranca,
            ':valor_mensal'     => round($valorMensal, 2),
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

        $stmt->execute([
            ':id' => $id,
                        ':user_id' => $this->userId,
            ':ativa' => $ativa,
        ]);

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
                'Manual'::text AS conta_nome,
                NULL::text AS conta_tipo,
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
        try {
            return (new DateTime($val))->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function ensureCategoriasTable(): void
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
