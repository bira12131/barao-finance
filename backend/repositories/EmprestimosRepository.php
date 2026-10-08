<?php
/**
 * REPOSITORY — EmprestimosRepository
 *
 * Contratos de empréstimo/financiamento. A API da Pierre não expõe contratos: eles aparecem
 * apenas como pagamentos nas transações. Por isso o app DETECTA pagamentos recorrentes e o
 * usuário confirma os dados do contrato (valor, início, nº de parcelas). A partir daí calculamos
 * parcelas pagas (pelo extrato), restantes, término e as parcelas previstas de cada mês.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/schema.php';

class EmprestimosRepository
{
    private PDO $pdo;
    private string $userId;

    public function __construct(string $userId)
    {
        $this->userId = trim($userId);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $this->userId)) {
            throw new InvalidArgumentException('userId inválido para EmprestimosRepository.');
        }

        $this->pdo = getConnection();
        schemaOnce($this->pdo, 'emprestimos_v1', fn() => $this->ensureTable());
        schemaOnce($this->pdo, 'emprestimos_adiantamentos_v1', fn() => $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS emprestimos_adiantamentos (
                id                UUID          DEFAULT gen_random_uuid() PRIMARY KEY,
                user_id           UUID          NOT NULL,
                emprestimo_id     UUID          NOT NULL,
                data              DATE          NOT NULL DEFAULT CURRENT_DATE,
                valor_pago        NUMERIC(15,2) NOT NULL,
                parcelas_quitadas INT           NOT NULL DEFAULT 0,
                criado_em         TIMESTAMPTZ   DEFAULT NOW()
            )'
        ));
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS emprestimos (
                id               UUID          DEFAULT gen_random_uuid() PRIMARY KEY,
                user_id          UUID          NOT NULL,
                nome             TEXT          NOT NULL,
                credor           TEXT,
                valor_parcela    NUMERIC(15,2) NOT NULL,
                primeira_parcela DATE          NOT NULL,
                total_parcelas   INT           NOT NULL,
                padrao_busca     TEXT,
                ativo            BOOLEAN       NOT NULL DEFAULT TRUE,
                criado_em        TIMESTAMPTZ   DEFAULT NOW(),
                atualizado_em    TIMESTAMPTZ   DEFAULT NOW()
            )"
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_emprestimos_user ON emprestimos (user_id)');
    }

    private function idValido(string $id): bool
    {
        return (bool)preg_match('/^[a-f0-9-]{36}$/i', $id);
    }

    /* ════════════════════════════════════════════
       Validação + CRUD
    ════════════════════════════════════════════ */

    public function normalizar(array $in): ?array
    {
        $nome   = trim((string)($in['nome'] ?? ''));
        $valor  = round((float)($in['valor_parcela'] ?? 0), 2);
        $total  = (int)($in['total_parcelas'] ?? 0);
        $inicio = trim((string)($in['primeira_parcela'] ?? ''));

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $inicio)) $inicio .= '-01';
        if ($nome === '' || $valor <= 0 || $total < 1 || $total > 480) return null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || !checkdate((int)substr($inicio, 5, 2), (int)substr($inicio, 8, 2), (int)substr($inicio, 0, 4))) return null;

        $padrao = mb_strtolower(trim((string)($in['padrao_busca'] ?? '')));
        $padrao = trim(preg_replace('/[%_\s]+/u', ' ', $padrao));   // sem curingas do usuário

        $credor = trim((string)($in['credor'] ?? ''));

        return [
            'nome'             => mb_substr($nome, 0, 120),
            'credor'           => $credor !== '' ? mb_substr($credor, 0, 120) : null,
            'valor_parcela'    => $valor,
            'primeira_parcela' => $inicio,
            'total_parcelas'   => $total,
            'padrao_busca'     => $padrao !== '' ? mb_substr($padrao, 0, 120) : null,
        ];
    }

    public function criar(array $e): string
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO emprestimos (user_id, nome, credor, valor_parcela, primeira_parcela, total_parcelas, padrao_busca)
             VALUES (:u, :nome, :credor, :valor, :inicio, :total, :padrao) RETURNING id'
        );
        $stmt->execute([
            ':u' => $this->userId, ':nome' => $e['nome'], ':credor' => $e['credor'], ':valor' => $e['valor_parcela'],
            ':inicio' => $e['primeira_parcela'], ':total' => $e['total_parcelas'], ':padrao' => $e['padrao_busca'],
        ]);
        return (string)$stmt->fetchColumn();
    }

    public function atualizar(string $id, array $e): bool
    {
        if (!$this->idValido($id)) return false;
        $stmt = $this->pdo->prepare(
            'UPDATE emprestimos
             SET nome = :nome, credor = :credor, valor_parcela = :valor, primeira_parcela = :inicio,
                 total_parcelas = :total, padrao_busca = :padrao, atualizado_em = NOW()
             WHERE id = :id AND user_id = :u'
        );
        $stmt->execute([
            ':id' => $id, ':u' => $this->userId, ':nome' => $e['nome'], ':credor' => $e['credor'], ':valor' => $e['valor_parcela'],
            ':inicio' => $e['primeira_parcela'], ':total' => $e['total_parcelas'], ':padrao' => $e['padrao_busca'],
        ]);
        return $stmt->rowCount() > 0;
    }

    public function excluir(string $id): bool
    {
        if (!$this->idValido($id)) return false;
        $this->pdo->prepare('DELETE FROM emprestimos_adiantamentos WHERE emprestimo_id = :id AND user_id = :u')
            ->execute([':id' => $id, ':u' => $this->userId]);
        $stmt = $this->pdo->prepare('DELETE FROM emprestimos WHERE id = :id AND user_id = :u');
        $stmt->execute([':id' => $id, ':u' => $this->userId]);
        return $stmt->rowCount() > 0;
    }

    /* ════════════════════════════════════════════
       Leitura com cálculo
    ════════════════════════════════════════════ */

    /**
     * Contratos com os adiantamentos já aplicados.
     *
     * Regra de abatimento: parcelas adiantadas quitam o contrato de TRÁS PARA FRENTE (menos parcelas,
     * término antecipado). Cada adiantamento abate parcelas_quitadas × parcela do saldo do contrato
     * (o valor pago pode ser menor, se houve desconto) ou, sem parcelas informadas, o próprio valor pago.
     * O que sobrar de parcela inteira reduz o valor da última parcela.
     */
    private function carregarContratos(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, credor, valor_parcela::float8 AS valor_parcela, primeira_parcela::text AS primeira_parcela,
                    total_parcelas, padrao_busca
             FROM emprestimos WHERE user_id = :u AND ativo = TRUE ORDER BY primeira_parcela ASC'
        );
        $stmt->execute([':u' => $this->userId]);
        $contratos = $stmt->fetchAll();

        $ad = $this->pdo->prepare(
            'SELECT id, emprestimo_id, data::text AS data, valor_pago::float8 AS valor_pago, parcelas_quitadas
             FROM emprestimos_adiantamentos WHERE user_id = :u ORDER BY data DESC, criado_em DESC'
        );
        $ad->execute([':u' => $this->userId]);
        $porContrato = [];
        foreach ($ad->fetchAll() as $a) $porContrato[$a['emprestimo_id']][] = $a;

        foreach ($contratos as &$c) {
            $parcela = (float)$c['valor_parcela'];
            $lista = $porContrato[$c['id']] ?? [];
            $abatimento = 0.0;
            $economia = 0.0;
            $valorPago = 0.0;
            foreach ($lista as $a) {
                $pq = (int)$a['parcelas_quitadas'];
                $abatimento += $pq > 0 ? $pq * $parcela : (float)$a['valor_pago'];
                if ($pq > 0) $economia += max(0, $pq * $parcela - (float)$a['valor_pago']);
                $valorPago += (float)$a['valor_pago'];
            }
            $eliminadas = (int)floor($abatimento / $parcela + 1e-9);
            $c['adiantamentos']   = $lista;
            $c['abatimento']      = round($abatimento, 2);
            $c['economia']        = round($economia, 2);
            $c['valor_adiantado'] = round($valorPago, 2);
            $c['eliminadas']      = $eliminadas;
            $c['parcial']         = round($abatimento - $eliminadas * $parcela, 2);   // abate a última parcela
            $c['total_efetivo']   = max(0, (int)$c['total_parcelas'] - $eliminadas);
        }
        unset($c);
        return $contratos;
    }

    /** Contratos com parcelas pagas/restantes, adiantamentos, término e próxima parcela. */
    public function listar(): array
    {
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $hoje = new DateTimeImmutable('today', $fuso);
        $out = [];
        foreach ($this->carregarContratos() as $r) {
            $total   = (int)$r['total_parcelas'];
            $totalEf = (int)$r['total_efetivo'];
            $parcela = (float)$r['valor_parcela'];
            $inicio  = new DateTimeImmutable($r['primeira_parcela'], $fuso);

            // Parcelas pagas: vale o MAIOR entre o calendário (vencimentos que já passaram) e o que o extrato mostra.
            // O extrato só tem alguns meses de histórico: sozinho, ele subconta contratos mais antigos e empurra
            // a "próxima parcela" para o passado. Pagamentos feitos por contas não conectadas também não aparecem lá.
            $pelaCalendario = 0;
            while ($pelaCalendario < $totalEf && $inicio->modify('+' . $pelaCalendario . ' months') <= $hoje) $pelaCalendario++;

            $peloExtrato = 0;
            if ($r['padrao_busca']) {
                $q = $this->pdo->prepare(
                    "SELECT COUNT(DISTINCT TO_CHAR(data, 'YYYY-MM')) FROM pierre_transacoes
                     WHERE user_id = :u AND tipo = 'DEBIT' AND data >= :ini
                       AND LOWER(descricao) LIKE :pad"
                );
                $q->execute([
                    ':u' => $this->userId,
                    ':ini' => $inicio->modify('-20 days')->format('Y-m-d'),
                    ':pad' => '%' . str_replace(' ', '%', $r['padrao_busca']) . '%',
                ]);
                $peloExtrato = (int)$q->fetchColumn();
            }

            $pagas = min(max($pelaCalendario, $peloExtrato), $totalEf);
            $origemPagas = $peloExtrato > $pelaCalendario ? 'extrato' : 'calendario';
            $restantes = max(0, $totalEf - $pagas);

            $valorRestante = $restantes > 0 ? max(0, round($restantes * $parcela - $r['parcial'], 2)) : 0.0;
            $fim = $totalEf > 0 ? $inicio->modify('+' . ($totalEf - 1) . ' months') : $inicio;
            $proxima = $restantes > 0 ? $inicio->modify('+' . $pagas . ' months') : null;

            $out[] = [
                'id'               => $r['id'],
                'nome'             => $r['nome'],
                'credor'           => $r['credor'],
                'valor_parcela'    => $parcela,
                'primeira_parcela' => $r['primeira_parcela'],
                'total_parcelas'   => $total,
                'total_efetivo'    => $totalEf,
                'padrao_busca'     => $r['padrao_busca'],
                'parcelas_pagas'   => $pagas,
                'origem_pagas'     => $origemPagas,
                'pagas_calendario' => min($pelaCalendario, $totalEf),
                'pagas_extrato'    => min($peloExtrato, $totalEf),
                'parcelas_adiantadas' => $r['eliminadas'],
                'valor_adiantado'  => $r['valor_adiantado'],
                'abatimento'       => $r['abatimento'],
                'economia'         => $r['economia'],
                'adiantamentos'    => $r['adiantamentos'],
                'parcelas_restantes' => $restantes,
                'parcela_final'    => ($restantes > 0 && $r['parcial'] > 0) ? round($parcela - $r['parcial'], 2) : null,
                'valor_restante'   => $valorRestante,
                'valor_total'      => round($total * $parcela, 2),
                'progresso'        => round(($total - $restantes) / $total * 100, 1),
                'ultima_parcela'   => $fim->format('Y-m-d'),
                'termino_original' => $inicio->modify('+' . ($total - 1) . ' months')->format('Y-m-d'),
                'proxima_parcela'  => $proxima ? $proxima->format('Y-m-d') : null,
                'quitado'          => $restantes === 0,
            ];
        }
        return $out;
    }

    /**
     * Registra um adiantamento. Retorna null se deu certo, ou a mensagem de erro.
     * Sem valor pago, usa parcelas × valor da parcela. Não pode passar do saldo restante.
     */
    public function adiantar(string $id, ?string $data, ?float $valorPago, int $parcelas): ?string
    {
        if (!$this->idValido($id)) return 'Contrato não encontrado.';
        $contrato = null;
        foreach ($this->listar() as $c) if ($c['id'] === $id) { $contrato = $c; break; }
        if ($contrato === null) return 'Contrato não encontrado.';
        if ($contrato['quitado']) return 'Este contrato já está quitado.';

        $parcelas = max(0, $parcelas);
        $parcela = $contrato['valor_parcela'];
        if (($valorPago === null || $valorPago <= 0) && $parcelas > 0) $valorPago = round($parcelas * $parcela, 2);
        if ($valorPago === null || $valorPago <= 0) return 'Informe quantas parcelas adiantou e/ou o valor pago.';

        $abatimento = $parcelas > 0 ? $parcelas * $parcela : $valorPago;
        if ($abatimento > $contrato['valor_restante'] + 0.009) {
            return 'O adiantamento passa do saldo restante do contrato (' . number_format($contrato['valor_restante'], 2, ',', '.') . ').';
        }

        $data = trim((string)$data);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !checkdate((int)substr($data, 5, 2), (int)substr($data, 8, 2), (int)substr($data, 0, 4))) {
            $data = date('Y-m-d');
        }

        $this->pdo->prepare(
            'INSERT INTO emprestimos_adiantamentos (user_id, emprestimo_id, data, valor_pago, parcelas_quitadas)
             VALUES (:u, :id, :data, :valor, :pq)'
        )->execute([':u' => $this->userId, ':id' => $id, ':data' => $data, ':valor' => round($valorPago, 2), ':pq' => $parcelas]);
        return null;
    }

    public function removerAdiantamento(string $adiantamentoId): bool
    {
        if (!$this->idValido($adiantamentoId)) return false;
        $stmt = $this->pdo->prepare('DELETE FROM emprestimos_adiantamentos WHERE id = :id AND user_id = :u');
        $stmt->execute([':id' => $adiantamentoId, ':u' => $this->userId]);
        return $stmt->rowCount() > 0;
    }

    /** Parcelas previstas em um mês (YYYY-MM), já com os adiantamentos abatidos. */
    public function parcelasDoMes(string $mes): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) return [];

        $alvo = new DateTimeImmutable($mes . '-01');
        $out = [];
        foreach ($this->carregarContratos() as $r) {
            $inicio = new DateTimeImmutable($r['primeira_parcela']);
            $inicioMes = new DateTimeImmutable($inicio->format('Y-m-01'));
            $n = ((int)$alvo->format('Y') - (int)$inicioMes->format('Y')) * 12 + ((int)$alvo->format('n') - (int)$inicioMes->format('n'));
            $totalEf = (int)$r['total_efetivo'];
            if ($n < 0 || $n >= $totalEf) continue;

            // A última parcela pode ser menor, se um adiantamento abateu só parte dela.
            $valor = ($n === $totalEf - 1 && $r['parcial'] > 0)
                ? max(0, round($r['valor_parcela'] - $r['parcial'], 2))
                : $r['valor_parcela'];

            $dia = min((int)$inicio->format('j'), (int)$alvo->format('t'));
            $out[] = [
                'id'        => $r['id'],
                'nome'      => $r['nome'],
                'credor'    => $r['credor'],
                'valor'     => $valor,
                'data'      => $alvo->format('Y-m-') . sprintf('%02d', $dia),
                'parcela'   => $n + 1,
                'total'     => $totalEf,
            ];
        }
        return $out;
    }

    /* ════════════════════════════════════════════
       Detecção de pagamentos recorrentes no extrato
    ════════════════════════════════════════════ */

    /** Sugestões de contratos ainda não cadastrados, vindas de pagamentos recorrentes. */
    public function detectar(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT descricao, data::text AS data, ABS(valor)::float8 AS valor, conta_nome
                 FROM pierre_transacoes
                 WHERE user_id = :u AND tipo = 'DEBIT'
                   AND data >= CURRENT_DATE - INTERVAL '400 days'
                   AND LOWER(COALESCE(categoria, '')) ~ 'empr[eé]stimo|financiamento'
                   AND LOWER(descricao) !~ 'quitado|ajuste|estorno'
                 ORDER BY data ASC"
            );
            $stmt->execute([':u' => $this->userId]);
            $linhas = $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }

        $grupos = [];
        foreach ($linhas as $l) {
            $limpo = preg_replace('/^.*\|/u', '', $l['descricao']);                 // "Pagamento efetuado|CREDOR" -> "CREDOR"
            if (trim($limpo) === '' || mb_strlen(trim($limpo)) < 4) $limpo = $l['descricao'];
            $chave = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\d\/\-\.]+/u', ' ', mb_strtolower($limpo))));
            if ($chave === '') continue;
            $grupos[$chave][] = $l + ['limpo' => trim(preg_replace('/\s+/u', ' ', $limpo))];
        }

        $existentes = array_filter(array_map(static fn($c) => $c['padrao_busca'], $this->listar()));

        $sugestoes = [];
        foreach ($grupos as $chave => $itens) {
            $meses = array_unique(array_map(static fn($i) => substr($i['data'], 0, 7), $itens));
            if (count($meses) < 2) continue;

            foreach ($existentes as $p) {
                if (strpos($chave, $p) !== false || strpos($p, $chave) !== false) continue 2;  // já cadastrado
            }

            $valores = array_column($itens, 'valor');
            $ultimo = end($itens);
            $primeira = $itens[0];
            $sugestoes[] = [
                'titulo'            => mb_convert_case($ultimo['limpo'], MB_CASE_TITLE, 'UTF-8'),
                'padrao_busca'      => $chave,
                'ocorrencias'       => count($itens),
                'meses'             => count($meses),
                'valor_ultimo'      => round((float)$ultimo['valor'], 2),
                'valor_medio'       => round(array_sum($valores) / count($valores), 2),
                'valor_min'         => round(min($valores), 2),
                'valor_max'         => round(max($valores), 2),
                'primeira_observada' => $primeira['data'],
                'ultima_observada'  => $ultimo['data'],
                'conta_nome'        => $ultimo['conta_nome'],
            ];
        }

        usort($sugestoes, static fn($a, $b) => $b['meses'] <=> $a['meses']);
        return $sugestoes;
    }
}
