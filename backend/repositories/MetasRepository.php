<?php
/**
 * REPOSITORY — MetasRepository
 *
 * Metas financeiras genéricas (casa, carro, viagem…), aportes, renda mensal
 * informada à mão e o cálculo de viabilidade (quanto guardar por mês).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/schema.php';
require_once __DIR__ . '/EmprestimosRepository.php';
require_once __DIR__ . '/InvestimentosRepository.php';

class MetasRepository
{
    private PDO $pdo;
    private string $userId;

    public function __construct(string $userId)
    {
        $this->userId = trim($userId);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $this->userId)) {
            throw new InvalidArgumentException('userId inválido para MetasRepository.');
        }

        $this->pdo = getConnection();
        schemaOnce($this->pdo, 'metas_v1', fn() => $this->ensureTables());
        schemaOnce($this->pdo, 'metas_contar_transf_v1', fn() => $this->pdo->exec(
            'ALTER TABLE perfil_financeiro ADD COLUMN IF NOT EXISTS contar_transferencias BOOLEAN NOT NULL DEFAULT TRUE'
        ));
        // Transferências a terceiros passam a ficar fora do gasto por padrão (eram contadas sem você escolher).
        schemaOnce($this->pdo, 'metas_contar_transf_v2', function () {
            $this->pdo->exec('ALTER TABLE perfil_financeiro ALTER COLUMN contar_transferencias SET DEFAULT FALSE');
            $this->pdo->exec('UPDATE perfil_financeiro SET contar_transferencias = FALSE');
        });
        schemaOnce($this->pdo, 'metas_destinos_v1', fn() => $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS destinos_ignorados (
                user_id   UUID         NOT NULL,
                destino   TEXT         NOT NULL,
                criado_em TIMESTAMPTZ  DEFAULT NOW()
            )'
        ));
        $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_destinos_ignorados ON destinos_ignorados (user_id, LOWER(destino))');
        // Gastos entram na análise a partir desta data (padrão: agosto/2026).
        schemaOnce($this->pdo, 'metas_analise_desde_v1', fn() => $this->pdo->exec(
            "ALTER TABLE perfil_financeiro ADD COLUMN IF NOT EXISTS analise_desde DATE DEFAULT DATE '2026-08-01'"
        ));
    }

    /** Destinatários (nome no extrato) que você marcou como "não é gasto": não entram nos totais nem nas Metas. */
    public function destinosIgnorados(): array
    {
        $stmt = $this->pdo->prepare('SELECT destino FROM destinos_ignorados WHERE user_id = :u ORDER BY destino');
        $stmt->execute([':u' => $this->userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function ignorarDestino(string $destino): bool
    {
        $destino = trim($destino);
        if ($destino === '' || mb_strlen($destino) > 160) return false;
        $this->pdo->prepare(
            'INSERT INTO destinos_ignorados (user_id, destino) VALUES (:u, :d) ON CONFLICT DO NOTHING'
        )->execute([':u' => $this->userId, ':d' => $destino]);
        return true;
    }

    public function desfazerDestino(string $destino): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM destinos_ignorados WHERE user_id = :u AND LOWER(destino) = LOWER(:d)');
        $stmt->execute([':u' => $this->userId, ':d' => trim($destino)]);
        return $stmt->rowCount() > 0;
    }

    private function getContarTransferencias(): bool
    {
        // fetchColumn() devolve false tanto para "sem linha" quanto para a coluna boolean false: por isso lê a linha inteira.
        $stmt = $this->pdo->prepare('SELECT CASE WHEN contar_transferencias THEN 1 ELSE 0 END AS v FROM perfil_financeiro WHERE user_id = :u');
        $stmt->execute([':u' => $this->userId]);
        $r = $stmt->fetch();
        return $r ? (int)$r['v'] === 1 : false;   // sem escolha sua: transferências a terceiros ficam FORA do gasto
    }

    /** Define se transferências/PIX para terceiros entram no gasto médio. */
    public function salvarContarTransferencias(bool $contar): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO perfil_financeiro (user_id, contar_transferencias, atualizado_em)
             VALUES (:u, :b, NOW())
             ON CONFLICT (user_id) DO UPDATE SET contar_transferencias = EXCLUDED.contar_transferencias, atualizado_em = NOW()'
        );
        $stmt->bindValue(':u', $this->userId);
        $stmt->bindValue(':b', $contar, PDO::PARAM_BOOL);   // PDO envia false como '' e o PostgreSQL recusa
        $stmt->execute();
    }

    private function getAnaliseDesde(): string
    {
        $stmt = $this->pdo->prepare('SELECT analise_desde::text FROM perfil_financeiro WHERE user_id = :u');
        $stmt->execute([':u' => $this->userId]);
        $v = $stmt->fetchColumn();
        return is_string($v) && $v !== '' ? $v : '2026-08-01';
    }

    private function ensureTables(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS metas (
                id            UUID          DEFAULT gen_random_uuid() PRIMARY KEY,
                user_id       UUID          NOT NULL,
                nome          TEXT          NOT NULL,
                componentes   JSONB         NOT NULL DEFAULT '[]'::jsonb,
                valor_alvo    NUMERIC(15,2) NOT NULL,
                valor_inicial NUMERIC(15,2) NOT NULL DEFAULT 0,
                data_alvo     DATE,
                status        VARCHAR(20)   NOT NULL DEFAULT 'ativa',
                criado_em     TIMESTAMPTZ   DEFAULT NOW(),
                atualizado_em TIMESTAMPTZ   DEFAULT NOW()
            )"
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_metas_user ON metas (user_id)');

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS metas_aportes (
                id        UUID          DEFAULT gen_random_uuid() PRIMARY KEY,
                meta_id   UUID          NOT NULL,
                user_id   UUID          NOT NULL,
                valor     NUMERIC(15,2) NOT NULL,
                data      DATE          NOT NULL DEFAULT CURRENT_DATE,
                criado_em TIMESTAMPTZ   DEFAULT NOW()
            )"
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_metas_aportes_meta ON metas_aportes (meta_id)');

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS perfil_financeiro (
                user_id       UUID          PRIMARY KEY,
                renda_mensal  NUMERIC(15,2),
                atualizado_em TIMESTAMPTZ   DEFAULT NOW()
            )"
        );
    }

    private function idValido(string $id): bool
    {
        return (bool)preg_match('/^[a-f0-9-]{36}$/i', $id);
    }

    /* ════════════════════════════════════════════
       Perfil (renda mensal informada à mão)
    ════════════════════════════════════════════ */

    public function getRendaMensal(): ?float
    {
        $stmt = $this->pdo->prepare('SELECT renda_mensal FROM perfil_financeiro WHERE user_id = :u');
        $stmt->execute([':u' => $this->userId]);
        $v = $stmt->fetchColumn();
        return ($v === false || $v === null) ? null : (float)$v;
    }

    public function salvarRendaMensal(?float $renda): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO perfil_financeiro (user_id, renda_mensal, atualizado_em)
             VALUES (:u, :r, NOW())
             ON CONFLICT (user_id) DO UPDATE SET renda_mensal = EXCLUDED.renda_mensal, atualizado_em = NOW()'
        );
        $stmt->execute([':u' => $this->userId, ':r' => $renda]);
    }

    /* ════════════════════════════════════════════
       Validação
    ════════════════════════════════════════════ */

    /** Normaliza a entrada de meta; retorna null se inválida. */
    public function normalizar(array $in): ?array
    {
        $nome = trim((string)($in['nome'] ?? ''));
        if ($nome === '') return null;

        $componentes = [];
        foreach ((array)($in['componentes'] ?? []) as $c) {
            $n = trim((string)($c['nome'] ?? ''));
            $v = round((float)($c['valor'] ?? 0), 2);
            if ($n === '' || $v <= 0) continue;
            $componentes[] = ['nome' => mb_substr($n, 0, 80), 'valor' => $v];
        }

        $valorAlvo = $componentes
            ? array_sum(array_column($componentes, 'valor'))
            : round((float)($in['valor_alvo'] ?? 0), 2);
        if ($valorAlvo <= 0) return null;

        $inicial = round(max(0, (float)($in['valor_inicial'] ?? 0)), 2);

        // data_alvo aceita YYYY-MM (vira o último dia do mês) ou YYYY-MM-DD
        $dataAlvo = trim((string)($in['data_alvo'] ?? ''));
        if ($dataAlvo === '') {
            $dataAlvo = null;
        } elseif (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $dataAlvo)) {
            $dataAlvo = date('Y-m-t', strtotime($dataAlvo . '-01'));
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAlvo) || !strtotime($dataAlvo)) {
            return null;
        }

        return [
            'nome'          => mb_substr($nome, 0, 120),
            'componentes'   => $componentes,
            'valor_alvo'    => round((float)$valorAlvo, 2),
            'valor_inicial' => $inicial,
            'data_alvo'     => $dataAlvo,
        ];
    }

    /* ════════════════════════════════════════════
       CRUD
    ════════════════════════════════════════════ */

    public function criar(array $m): string
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO metas (user_id, nome, componentes, valor_alvo, valor_inicial, data_alvo)
             VALUES (:u, :nome, :comp::jsonb, :alvo, :ini, :data)
             RETURNING id'
        );
        $stmt->execute([
            ':u' => $this->userId, ':nome' => $m['nome'],
            ':comp' => json_encode($m['componentes'], JSON_UNESCAPED_UNICODE),
            ':alvo' => $m['valor_alvo'], ':ini' => $m['valor_inicial'], ':data' => $m['data_alvo'],
        ]);
        return (string)$stmt->fetchColumn();
    }

    public function atualizar(string $id, array $m): bool
    {
        if (!$this->idValido($id)) return false;
        $stmt = $this->pdo->prepare(
            'UPDATE metas
             SET nome = :nome, componentes = :comp::jsonb, valor_alvo = :alvo,
                 valor_inicial = :ini, data_alvo = :data, atualizado_em = NOW()
             WHERE id = :id AND user_id = :u'
        );
        $stmt->execute([
            ':id' => $id, ':u' => $this->userId, ':nome' => $m['nome'],
            ':comp' => json_encode($m['componentes'], JSON_UNESCAPED_UNICODE),
            ':alvo' => $m['valor_alvo'], ':ini' => $m['valor_inicial'], ':data' => $m['data_alvo'],
        ]);
        return $stmt->rowCount() > 0;
    }

    public function excluir(string $id): bool
    {
        if (!$this->idValido($id)) return false;
        $this->pdo->prepare('DELETE FROM metas_aportes WHERE meta_id = :id AND user_id = :u')
            ->execute([':id' => $id, ':u' => $this->userId]);
        $stmt = $this->pdo->prepare('DELETE FROM metas WHERE id = :id AND user_id = :u');
        $stmt->execute([':id' => $id, ':u' => $this->userId]);
        return $stmt->rowCount() > 0;
    }

    /** Registra aporte (positivo) ou retirada (negativo); impede saldo da meta negativo. */
    public function aportar(string $id, float $valor): bool
    {
        if (!$this->idValido($id) || abs($valor) < 0.01) return false;

        $stmt = $this->pdo->prepare(
            'SELECT valor_inicial + COALESCE((SELECT SUM(valor) FROM metas_aportes WHERE meta_id = metas.id), 0)
             FROM metas WHERE id = :id AND user_id = :u'
        );
        $stmt->execute([':id' => $id, ':u' => $this->userId]);
        $guardado = $stmt->fetchColumn();
        if ($guardado === false || ((float)$guardado + $valor) < 0) return false;

        $this->pdo->prepare('INSERT INTO metas_aportes (meta_id, user_id, valor) VALUES (:id, :u, :v)')
            ->execute([':id' => $id, ':u' => $this->userId, ':v' => round($valor, 2)]);
        return true;
    }

    /* ════════════════════════════════════════════
       Leitura + cálculo
    ════════════════════════════════════════════ */

    /** Retorna metas calculadas, resumo financeiro e a situação geral. */
    public function visaoGeral(): array
    {
        $renda = $this->getRendaMensal();

        // Empréstimos cadastrados: a parcela contratada substitui os pagamentos soltos do extrato.
        $emp = ['parcela_mensal' => 0.0, 'contratos' => 0, 'termina_em' => null];
        try {
            foreach ((new EmprestimosRepository($this->userId))->listar() as $c) {
                if ($c['quitado']) continue;
                $emp['parcela_mensal'] += $c['valor_parcela'];
                $emp['contratos']++;
                if ($emp['termina_em'] === null || $c['ultima_parcela'] > $emp['termina_em']) $emp['termina_em'] = $c['ultima_parcela'];
            }
        } catch (\Throwable $e) {
            // sem empréstimos cadastrados
        }
        $emp['parcela_mensal'] = round($emp['parcela_mensal'], 2);

        $contarTransf = $this->getContarTransferencias();
        $gastos = $this->gastoMedioMensal($emp['contratos'] > 0, $contarTransf);
        if ($emp['contratos'] > 0) {
            $gastos['media'] = round($gastos['media'] + $emp['parcela_mensal'], 2);
            $gastos['meses'] = max($gastos['meses'], 1);
            $gastos['categorias'][] = ['categoria' => 'Empréstimos e financiamento', 'media_mensal' => $emp['parcela_mensal']];
            usort($gastos['categorias'], static fn($a, $b) => $b['media_mensal'] <=> $a['media_mensal']);
            $gastos['categorias'] = array_slice($gastos['categorias'], 0, 5);
        }
        $capacidade = $renda !== null ? round($renda - $gastos['media'], 2) : null;

        // Investimentos (saldo informado por você; senão, o que a Pierre informa) contam como já guardado.
        $investido = 0.0;
        try {
            foreach ((new InvestimentosRepository($this->userId))->porConta() as $c) {
                $investido += $c['saldo_informado'] !== null ? $c['saldo_informado'] : $c['saldo_api'];
            }
        } catch (\Throwable $e) {
            // sem contas sincronizadas
        }
        $investido = round($investido, 2);
        $investimentoRestante = $investido;

        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.nome, m.componentes, m.valor_alvo, m.valor_inicial, m.data_alvo, m.status,
                    COALESCE(SUM(a.valor), 0) AS aportes
             FROM metas m
             LEFT JOIN metas_aportes a ON a.meta_id = m.id
             WHERE m.user_id = :u
             GROUP BY m.id
             ORDER BY m.data_alvo ASC NULLS LAST, m.criado_em ASC"
        );
        $stmt->execute([':u' => $this->userId]);

        $metas = [];
        $necessarioTotal = 0.0;
        foreach ($stmt->fetchAll() as $r) {
            // Distribui os investimentos pelas metas na ordem (prazo mais próximo primeiro) até cobrir cada uma.
            $faltaManual = max(0, (float)$r['valor_alvo'] - ((float)$r['valor_inicial'] + (float)$r['aportes']));
            $alocado = round(min($faltaManual, $investimentoRestante), 2);
            $investimentoRestante = round($investimentoRestante - $alocado, 2);
            $meta = $this->calcularMeta($r, $capacidade, $alocado);
            if ($meta['por_mes'] !== null && !$meta['concluida']) {
                $necessarioTotal += $meta['por_mes'];
            }
            $metas[] = $meta;
        }
        $necessarioTotal = round($necessarioTotal, 2);

        $situacao = null;
        if ($capacidade !== null && $necessarioTotal > 0) {
            $situacao = $necessarioTotal <= $capacidade * 0.8 ? 'viavel'
                      : ($necessarioTotal <= $capacidade ? 'apertado' : 'inviavel');
        }

        return [
            'metas' => $metas,
            'perfil' => ['renda_mensal' => $renda, 'contar_transferencias' => $contarTransf],
            'analise' => [
                'gasto_medio_mensal'  => $gastos['media'],
                'meses_considerados'  => $gastos['meses'],
                'base_gasto'          => $gastos['base'],
                'capacidade_mensal'   => $capacidade,
                'necessario_mes_total' => $necessarioTotal,
                'deficit_mensal'      => $capacidade !== null ? round(max(0, $necessarioTotal - $capacidade), 2) : null,
                'situacao'            => $situacao,
                'top_categorias'      => $gastos['categorias'],
                'analise_desde'       => $this->getAnaliseDesde(),
                'investido_total'     => $investido,
                'emprestimos'         => $emp,
                'transferencias_mensal' => $gastos['transferencias'] ?? 0.0,
                'transferencias_por_mes' => $gastos['transferencias_por_mes'] ?? [],
                'transferencias_detalhe' => $gastos['transferencias_detalhe'] ?? [],
                'destinos_ignorados'  => $this->destinosIgnorados(),
            ],
        ];
    }

    private function calcularMeta(array $r, ?float $capacidade, float $investimentoAlocado = 0.0): array
    {
        $alvo     = (float)$r['valor_alvo'];
        $guardado = round((float)$r['valor_inicial'] + (float)$r['aportes'] + $investimentoAlocado, 2);
        $restante = round(max(0, $alvo - $guardado), 2);
        $concluida = $restante <= 0;
        $hoje     = new DateTimeImmutable('today');

        $mesesRestantes = null;
        $porMes = null;
        $dataAlvo = $r['data_alvo'] ? substr((string)$r['data_alvo'], 0, 10) : null;
        if ($dataAlvo !== null) {
            $alvoDt = new DateTimeImmutable($dataAlvo);
            $mesesRestantes = max(1, ((int)$alvoDt->format('Y') - (int)$hoje->format('Y')) * 12
                + ((int)$alvoDt->format('n') - (int)$hoje->format('n')));
            $porMes = $concluida ? 0.0 : round($restante / $mesesRestantes, 2);
        }

        $cenarios = [];
        if (!$concluida) {
            foreach ([12, 24, 36, 48, 60] as $n) {
                $cenarios[] = ['meses' => $n, 'por_mes' => round($restante / $n, 2)];
            }
        }

        $mesesEstimados = null;
        $dataEstimada = null;
        if (!$concluida && $capacidade !== null && $capacidade > 0) {
            $mesesEstimados = (int)ceil($restante / $capacidade);
            $dataEstimada = $hoje->modify('+' . $mesesEstimados . ' months')->format('Y-m');
        }

        $viabilidade = null;
        $deficit = null;
        if (!$concluida && $porMes !== null && $capacidade !== null) {
            $viabilidade = $porMes <= $capacidade * 0.8 ? 'viavel' : ($porMes <= $capacidade ? 'apertado' : 'inviavel');
            $deficit = round(max(0, $porMes - $capacidade), 2);
        }

        return [
            'id'              => $r['id'],
            'nome'            => $r['nome'],
            'componentes'     => json_decode($r['componentes'] ?? '[]', true) ?: [],
            'valor_alvo'      => $alvo,
            'valor_inicial'   => (float)$r['valor_inicial'],
            'valor_guardado'  => $guardado,
            'investimento_alocado' => round($investimentoAlocado, 2),
            'restante'        => $restante,
            'progresso'       => $alvo > 0 ? round(min(1, $guardado / $alvo) * 100, 1) : 0,
            'concluida'       => $concluida,
            'data_alvo'       => $dataAlvo,
            'meses_restantes' => $mesesRestantes,
            'por_mes'         => $porMes,
            'cenarios'        => $cenarios,
            'meses_estimados' => $mesesEstimados,
            'data_estimada'   => $dataEstimada,
            'viabilidade'     => $viabilidade,
            'deficit_mensal'  => $deficit,
        ];
    }

    /**
     * Filtro SQL (sobre pierre_transacoes) do que conta como gasto. Parâmetros: :u, :i, :f.
     */
    private function filtroGasto(bool $excluirEmprestimos): string
    {
        // Fora do gasto: pagamento de fatura (as compras do cartão já contam), aplicações e
        // transferências entre contas próprias. Mantém a mesma regra de js/finance-rules.js.
        return "user_id = :u AND tipo = 'DEBIT' AND data BETWEEN :i AND :f
            AND COALESCE(LOWER(categoria), '') !~ '(pagamento de cart|investiment|mesma titularidade|mesma institui)'
            AND LOWER(descricao) !~ '(pagamento de fatura|aplica[cç][aã]o rdb|resgate rdb)'
            AND NOT (COALESCE(conta_tipo, '') <> 'CREDIT' AND EXISTS (
                    SELECT 1 FROM pierre_faturas f
                    WHERE f.user_id = pierre_transacoes.user_id AND f.total > 0 AND f.fechamento IS NOT NULL AND f.vencimento IS NOT NULL
                      AND ABS(ABS(valor) - f.total) < 0.02
                      AND data BETWEEN f.fechamento AND f.vencimento + 45))
            AND NOT EXISTS (
                    SELECT 1 FROM destinos_ignorados di
                    WHERE di.user_id = pierre_transacoes.user_id
                      AND LOWER(di.destino) = LOWER(TRIM(REGEXP_REPLACE(pierre_transacoes.descricao, '^.*\\|\\s*', ''))))"
            . ($excluirEmprestimos ? " AND COALESCE(LOWER(categoria), '') !~ 'empr[eé]stimo|financiamento'" : '');
    }

    /**
     * Gasto do mês corrente (mesmas regras da média): total até hoje, total de hoje e por categoria.
     * Com empréstimos cadastrados, soma as parcelas contratadas do mês no lugar dos pagamentos soltos.
     */
    public function gastoDoMes(): array
    {
        $hoje = new DateTimeImmutable('today');
        $mes  = $hoje->format('Y-m');

        $parcelasEmprestimo = 0.0;
        $temContratos = false;
        try {
            $parcelas = (new EmprestimosRepository($this->userId))->parcelasDoMes($mes);
            $temContratos = count($parcelas) > 0;
            foreach ($parcelas as $p) $parcelasEmprestimo += (float)$p['valor'];
        } catch (\Throwable $e) {
            // sem empréstimos
        }

        $filtro = $this->filtroGasto($temContratos);
        if (!$this->getContarTransferencias()) {
            $filtro .= " AND NOT (COALESCE(LOWER(categoria), '') ~ 'transfer|pix')";
        }
        $params = [':u' => $this->userId, ':i' => $hoje->format('Y-m-01'), ':f' => $hoje->format('Y-m-d')];

        try {
            $q = $this->pdo->prepare(
                "SELECT COALESCE(SUM(ABS(valor)), 0) AS total,
                        COALESCE(SUM(ABS(valor)) FILTER (WHERE data = :hoje), 0) AS hoje
                 FROM pierre_transacoes WHERE $filtro"
            );
            $q->execute($params + [':hoje' => $hoje->format('Y-m-d')]);
            $r = $q->fetch();

            $c = $this->pdo->prepare(
                "SELECT COALESCE(NULLIF(TRIM(categoria), ''), 'Sem categoria') AS categoria, SUM(ABS(valor)) AS total
                 FROM pierre_transacoes WHERE $filtro GROUP BY 1 ORDER BY total DESC LIMIT 8"
            );
            $c->execute($params);
            $categorias = array_map(
                static fn($x) => ['categoria' => $x['categoria'], 'total' => round((float)$x['total'], 2)],
                $c->fetchAll()
            );
        } catch (\Throwable $e) {
            return ['total' => round($parcelasEmprestimo, 2), 'hoje' => 0.0, 'emprestimos_mes' => round($parcelasEmprestimo, 2), 'categorias' => []];
        }

        return [
            'total'           => round((float)$r['total'] + $parcelasEmprestimo, 2),
            'hoje'            => round((float)$r['hoje'], 2),
            'emprestimos_mes' => round($parcelasEmprestimo, 2),
            'categorias'      => $categorias,
        ];
    }

    /**
     * Gasto médio mensal real: meses COMPLETOS a partir de analise_desde, ignorando pagamento de fatura
     * (já contado nas compras do cartão), transferências entre contas próprias e aplicações.
     * Se o mês mais antigo estiver incompleto (início do histórico), ele é descartado.
     */
    private function gastoMedioMensal(bool $excluirEmprestimos = false, bool $contarTransferencias = true): array
    {
        $vazio = ['media' => 0.0, 'meses' => 0, 'base' => 'sem_dados', 'categorias' => [], 'transferencias' => 0.0, 'transferencias_por_mes' => [], 'transferencias_detalhe' => []];

        try {
            $min = $this->pdo->prepare('SELECT MIN(data) FROM pierre_transacoes WHERE user_id = :u');
            $min->execute([':u' => $this->userId]);
            $primeira = $min->fetchColumn();
            if (!$primeira) return $vazio;

            $primeiroMesCompleto = new DateTimeImmutable(substr((string)$primeira, 0, 7) . '-01');
            if ((int)substr((string)$primeira, 8, 2) > 5) {
                $primeiroMesCompleto = $primeiroMesCompleto->modify('+1 month');
            }

            $mesAtual = new DateTimeImmutable('first day of this month');
            // Só entram meses a partir de analise_desde (padrão agosto/2026), nunca antes do 1º mês completo.
            $inicio = new DateTimeImmutable(substr($this->getAnaliseDesde(), 0, 7) . '-01');
            if ($inicio < $primeiroMesCompleto) $inicio = $primeiroMesCompleto;
            $fim = $mesAtual->modify('-1 day');
            $base = 'meses_completos';

            if ($inicio > $fim) {
                // Ainda sem mês completo: usa o mês corrente até agora.
                $inicio = $mesAtual;
                $fim = new DateTimeImmutable('today');
                $base = 'mes_atual_parcial';
            }

            $filtro = $this->filtroGasto($excluirEmprestimos);
            $params = [':u' => $this->userId, ':i' => $inicio->format('Y-m-d'), ':f' => $fim->format('Y-m-d')];

            // Transferências/PIX para terceiros: sempre medimos o valor; só entram na média se você quiser.
            $regexTransf = "COALESCE(LOWER(categoria), '') ~ 'transfer|pix'";
            $qt = $this->pdo->prepare("SELECT TO_CHAR(data, 'YYYY-MM') AS mes, SUM(ABS(valor))::float8 AS total FROM pierre_transacoes WHERE $filtro AND $regexTransf GROUP BY 1 ORDER BY 1");
            $qt->execute($params);
            $transfPorMes = $qt->fetchAll();
            $totalTransf = array_sum(array_column($transfPorMes, 'total'));

            // As maiores, para você reconhecer o que são (o nome de quem recebeu vem do extrato).
            $qd = $this->pdo->prepare("SELECT data::text AS data, descricao, ABS(valor)::float8 AS valor, conta_nome FROM pierre_transacoes WHERE $filtro AND $regexTransf ORDER BY ABS(valor) DESC LIMIT 8");
            $qd->execute($params);
            $transfDetalhe = array_map(static function ($x) {
                $nome = trim((string)preg_replace('/^.*\|/u', '', (string)$x['descricao']));
                return ['data' => $x['data'], 'destino' => $nome !== '' ? $nome : $x['descricao'], 'valor' => round((float)$x['valor'], 2), 'conta' => $x['conta_nome']];
            }, $qd->fetchAll());

            if (!$contarTransferencias) $filtro .= " AND NOT ($regexTransf)";

            $q = $this->pdo->prepare(
                "SELECT COALESCE(SUM(ABS(valor)), 0) AS total, COUNT(DISTINCT TO_CHAR(data, 'YYYY-MM')) AS meses
                 FROM pierre_transacoes WHERE $filtro"
            );
            $q->execute($params);
            $row = $q->fetch();
            $meses = max(1, (int)$row['meses']);
            $mesesJanela = max(1, ((int)$fim->format('Y') - (int)$inicio->format('Y')) * 12 + ((int)$fim->format('n') - (int)$inicio->format('n')) + 1);
            $total = (float)$row['total'];
            if ($total <= 0) return $vazio;

            $c = $this->pdo->prepare(
                "SELECT COALESCE(NULLIF(TRIM(categoria), ''), 'Sem categoria') AS categoria, SUM(ABS(valor)) AS total
                 FROM pierre_transacoes WHERE $filtro
                 GROUP BY 1 ORDER BY total DESC LIMIT 5"
            );
            $c->execute($params);
            $categorias = array_map(
                static fn($x) => ['categoria' => $x['categoria'], 'media_mensal' => round((float)$x['total'] / $meses, 2)],
                $c->fetchAll()
            );

            return [
                'media' => round($total / $meses, 2),
                'meses' => $meses,
                'base' => $base,
                'categorias' => $categorias,
                // média sobre TODOS os meses analisados (não só os meses em que houve transferência)
                'transferencias' => round((float)$totalTransf / max(1, $mesesJanela), 2),
                'transferencias_por_mes' => array_map(static fn($x) => ['mes' => $x['mes'], 'total' => round((float)$x['total'], 2)], $transfPorMes),
                'transferencias_detalhe' => $transfDetalhe,
            ];
        } catch (\Throwable $e) {
            return $vazio; // sem dados financeiros sincronizados
        }
    }
}
