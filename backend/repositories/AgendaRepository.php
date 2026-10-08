<?php
/**
 * REPOSITORY — AgendaRepository
 *
 * Compromissos, lembretes e tarefas do usuário (tabela agenda_eventos)
 * + vencimentos financeiros automáticos (faturas, despesas previstas, assinaturas).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/schema.php';
require_once __DIR__ . '/PierreRepository.php';
require_once __DIR__ . '/EmprestimosRepository.php';

class AgendaRepository
{
    public const TIPOS        = ['compromisso', 'lembrete', 'tarefa'];
    public const RECORRENCIAS = ['nenhuma', 'semanal', 'mensal', 'anual'];

    private PDO $pdo;
    private string $userId;

    public function __construct(string $userId)
    {
        $this->userId = trim($userId);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $this->userId)) {
            throw new InvalidArgumentException('userId inválido para AgendaRepository.');
        }

        $this->pdo = getConnection();
        schemaOnce($this->pdo, 'agenda_v1', fn() => $this->ensureTable());
        schemaOnce($this->pdo, 'agenda_icloud_v1', function () {
            $this->pdo->exec(
                "ALTER TABLE agenda_eventos
                    ADD COLUMN IF NOT EXISTS origem VARCHAR(20) NOT NULL DEFAULT 'app',
                    ADD COLUMN IF NOT EXISTS icloud_uid TEXT,
                    ADD COLUMN IF NOT EXISTS icloud_href TEXT,
                    ADD COLUMN IF NOT EXISTS icloud_etag TEXT,
                    ADD COLUMN IF NOT EXISTS icloud_cal_href TEXT,
                    ADD COLUMN IF NOT EXISTS icloud_cal_nome TEXT,
                    ADD COLUMN IF NOT EXISTS icloud_sync_em TIMESTAMPTZ"
            );
            $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_agenda_icloud_uid ON agenda_eventos (user_id, icloud_uid) WHERE icloud_uid IS NOT NULL');
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS icloud_sync (
                    user_id          UUID         PRIMARY KEY,
                    habilitado       BOOLEAN      NOT NULL DEFAULT FALSE,
                    calendario_href  TEXT,
                    home_url         TEXT,
                    calendarios      JSONB        NOT NULL DEFAULT \'[]\'::jsonb,
                    ultima_sync      TIMESTAMPTZ,
                    ultimo_resultado TEXT,
                    atualizado_em    TIMESTAMPTZ  DEFAULT NOW()
                )'
            );
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS agenda_icloud_exclusoes (
                    user_id   UUID        NOT NULL,
                    href      TEXT        NOT NULL,
                    etag      TEXT,
                    criado_em TIMESTAMPTZ DEFAULT NOW(),
                    PRIMARY KEY (user_id, href)
                )'
            );
        });
        schemaOnce($this->pdo, 'agenda_icloud_venc_v1', fn() => $this->pdo->exec(
            'ALTER TABLE icloud_sync ADD COLUMN IF NOT EXISTS calendario_venc_href TEXT'
        ));
        schemaOnce($this->pdo, 'agenda_ics_v1', fn() => $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS agenda_ics_tokens (
                user_id   UUID         PRIMARY KEY,
                token     VARCHAR(64)  NOT NULL UNIQUE,
                criado_em TIMESTAMPTZ  DEFAULT NOW()
            )'
        ));
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS agenda_eventos (
                id           UUID         DEFAULT gen_random_uuid() PRIMARY KEY,
                user_id      UUID         NOT NULL,
                titulo       TEXT         NOT NULL,
                descricao    TEXT,
                tipo         VARCHAR(20)  NOT NULL DEFAULT 'compromisso',
                data_inicio  DATE         NOT NULL,
                hora         TIME,
                recorrencia  VARCHAR(20)  NOT NULL DEFAULT 'nenhuma',
                concluido    BOOLEAN      NOT NULL DEFAULT FALSE,
                criado_em    TIMESTAMPTZ  DEFAULT NOW(),
                atualizado_em TIMESTAMPTZ DEFAULT NOW()
            )"
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_agenda_user_data ON agenda_eventos (user_id, data_inicio)');
    }

    /* ════════════════════════════════════════════
       Validação
    ════════════════════════════════════════════ */

    /** Normaliza e valida a entrada; retorna null se inválida. */
    public function normalizar(array $in): ?array
    {
        $titulo = trim((string)($in['titulo'] ?? ''));
        $data   = trim((string)($in['data'] ?? ''));
        $hora   = trim((string)($in['hora'] ?? ''));
        $tipo   = (string)($in['tipo'] ?? 'compromisso');
        $rec    = (string)($in['recorrencia'] ?? 'nenhuma');

        if ($titulo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) return null;
        [$y, $m, $d] = array_map('intval', explode('-', $data));
        if (!checkdate($m, $d, $y)) return null;
        if ($hora !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) return null;
        if (!in_array($tipo, self::TIPOS, true) || !in_array($rec, self::RECORRENCIAS, true)) return null;

        $descricao = trim((string)($in['descricao'] ?? ''));

        return [
            'titulo'      => mb_substr($titulo, 0, 160),
            'descricao'   => $descricao !== '' ? mb_substr($descricao, 0, 1000) : null,
            'tipo'        => $tipo,
            'data'        => $data,
            'hora'        => $hora !== '' ? $hora : null,
            'recorrencia' => $rec,
        ];
    }

    /* ════════════════════════════════════════════
       CRUD
    ════════════════════════════════════════════ */

    public function criar(array $e): string
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO agenda_eventos (user_id, titulo, descricao, tipo, data_inicio, hora, recorrencia)
             VALUES (:u, :titulo, :descricao, :tipo, :data, :hora, :rec)
             RETURNING id'
        );
        $stmt->execute([
            ':u' => $this->userId, ':titulo' => $e['titulo'], ':descricao' => $e['descricao'],
            ':tipo' => $e['tipo'], ':data' => $e['data'], ':hora' => $e['hora'], ':rec' => $e['recorrencia'],
        ]);
        return (string)$stmt->fetchColumn();
    }

    public function atualizar(string $id, array $e): bool
    {
        if (!$this->idValido($id)) return false;
        $stmt = $this->pdo->prepare(
            'UPDATE agenda_eventos
             SET titulo = :titulo, descricao = :descricao, tipo = :tipo, data_inicio = :data,
                 hora = :hora, recorrencia = :rec, atualizado_em = NOW()
             WHERE id = :id AND user_id = :u AND origem = \'app\''
        );
        $stmt->execute([
            ':id' => $id, ':u' => $this->userId, ':titulo' => $e['titulo'], ':descricao' => $e['descricao'],
            ':tipo' => $e['tipo'], ':data' => $e['data'], ':hora' => $e['hora'], ':rec' => $e['recorrencia'],
        ]);
        return $stmt->rowCount() > 0;
    }

    public function concluir(string $id, bool $concluido): bool
    {
        if (!$this->idValido($id)) return false;
        $stmt = $this->pdo->prepare(
            'UPDATE agenda_eventos SET concluido = :c, atualizado_em = NOW() WHERE id = :id AND user_id = :u AND origem = \'app\''
        );
        $stmt->bindValue(':c', $concluido, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':u', $this->userId);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function excluir(string $id): bool
    {
        if (!$this->idValido($id)) return false;
        $sel = $this->pdo->prepare('SELECT icloud_href, icloud_etag FROM agenda_eventos WHERE id = :id AND user_id = :u AND origem = \'app\'');
        $sel->execute([':id' => $id, ':u' => $this->userId]);
        $ev = $sel->fetch();
        if (!$ev) return false;

        // Já estava no iPhone: guarda a exclusão para a próxima sincronização apagar lá também.
        if (!empty($ev['icloud_href'])) {
            $this->pdo->prepare(
                'INSERT INTO agenda_icloud_exclusoes (user_id, href, etag) VALUES (:u, :h, :e)
                 ON CONFLICT (user_id, href) DO UPDATE SET etag = EXCLUDED.etag'
            )->execute([':u' => $this->userId, ':h' => $ev['icloud_href'], ':e' => $ev['icloud_etag']]);
        }

        $stmt = $this->pdo->prepare('DELETE FROM agenda_eventos WHERE id = :id AND user_id = :u');
        $stmt->execute([':id' => $id, ':u' => $this->userId]);
        return $stmt->rowCount() > 0;
    }

    private function idValido(string $id): bool
    {
        return (bool)preg_match('/^[a-f0-9-]{36}$/i', $id);
    }

    /* ════════════════════════════════════════════
       Consulta por período (expande recorrências)
    ════════════════════════════════════════════ */

    /**
     * Itens da agenda entre $inicio e $fim (YYYY-MM-DD, inclusivo), ordenados por data/hora.
     * Inclui eventos do usuário (com recorrência expandida) e vencimentos financeiros.
     */
    public function listarPeriodo(string $inicio, string $fim): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, titulo, descricao, tipo, data_inicio, TO_CHAR(hora, 'HH24:MI') AS hora,
                    recorrencia, concluido, origem, icloud_cal_nome
             FROM agenda_eventos
             WHERE user_id = :u
               AND ( (recorrencia = 'nenhuma' AND data_inicio BETWEEN :i AND :f)
                  OR (recorrencia <> 'nenhuma' AND data_inicio <= :f) )"
        );
        $stmt->execute([':u' => $this->userId, ':i' => $inicio, ':f' => $fim]);

        $itens = [];
        foreach ($stmt->fetchAll() as $r) {
            foreach ($this->expandir($r['data_inicio'], $r['recorrencia'], $inicio, $fim) as $data) {
                $itens[] = [
                    'id'          => $r['id'],
                    'origem'      => $r['origem'] === 'icloud' ? 'icloud' : 'agenda',
                    'icloud_cal_nome' => $r['icloud_cal_nome'],
                    'titulo'      => $r['titulo'],
                    'descricao'   => $r['descricao'],
                    'tipo'        => $r['tipo'],
                    'data'        => $data,
                    'data_inicio' => $r['data_inicio'],
                    'hora'        => $r['hora'],
                    'recorrencia' => $r['recorrencia'],
                    'concluido'   => $r['recorrencia'] === 'nenhuma' ? (bool)$r['concluido'] : false,
                ];
            }
        }

        $itens = array_merge($itens, $this->vencimentosFinanceiros($inicio, $fim));

        usort($itens, static function (array $a, array $b): int {
            return [$a['data'], $a['hora'] ?? '99:99'] <=> [$b['data'], $b['hora'] ?? '99:99'];
        });

        return $itens;
    }

    /* ════════════════════════════════════════════
       Assinatura de calendário (iPhone) por link secreto
    ════════════════════════════════════════════ */

    /** Token do link do calendário; cria um se ainda não existir. */
    public function tokenIcs(bool $regenerar = false): string
    {
        if (!$regenerar) {
            $stmt = $this->pdo->prepare('SELECT token FROM agenda_ics_tokens WHERE user_id = :u');
            $stmt->execute([':u' => $this->userId]);
            $t = $stmt->fetchColumn();
            if (is_string($t) && $t !== '') return $t;
        }

        $token = bin2hex(random_bytes(24));   // 192 bits: inviável de adivinhar
        $this->pdo->prepare(
            'INSERT INTO agenda_ics_tokens (user_id, token, criado_em) VALUES (:u, :t, NOW())
             ON CONFLICT (user_id) DO UPDATE SET token = EXCLUDED.token, criado_em = NOW()'
        )->execute([':u' => $this->userId, ':t' => $token]);
        return $token;
    }

    /** user_id dono do token (usado pelo endpoint público do calendário). */
    public static function usuarioPorTokenIcs(PDO $pdo, string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
        try {
            $stmt = $pdo->prepare('SELECT user_id FROM agenda_ics_tokens WHERE token = :t');
            $stmt->execute([':t' => $token]);
            $u = $stmt->fetchColumn();
            return is_string($u) ? $u : null;
        } catch (\Throwable $e) {
            return null;   // tabela ainda não existe: ninguém gerou link
        }
    }

    /** Eventos do usuário sem expandir recorrência (o .ics usa RRULE). */
    public function eventosBrutos(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, titulo, descricao, tipo, data_inicio::text AS data_inicio, TO_CHAR(hora, 'HH24:MI') AS hora,
                    recorrencia, concluido
             FROM agenda_eventos WHERE user_id = :u AND origem = 'app' ORDER BY data_inicio ASC"
        );
        $stmt->execute([':u' => $this->userId]);
        return $stmt->fetchAll();
    }

    /** A sincronização completa com o iCloud está ligada? (então o feed .ics só leva vencimentos) */
    public function icloudHabilitado(): bool
    {
        $stmt = $this->pdo->prepare('SELECT CASE WHEN habilitado THEN 1 ELSE 0 END FROM icloud_sync WHERE user_id = :u');
        $stmt->execute([':u' => $this->userId]);
        return (int)$stmt->fetchColumn() === 1;
    }

    /** Vencimentos financeiros (fatura, assinatura, empréstimo, despesa prevista) entre duas datas. */
    public function vencimentosEntre(string $inicio, string $fim): array
    {
        return $this->vencimentosFinanceiros($inicio, $fim);
    }

    /** Datas de ocorrência dentro de [inicio, fim]. */
    private function expandir(string $dataInicio, string $rec, string $inicio, string $fim): array
    {
        if ($rec === 'nenhuma') {
            return ($dataInicio >= $inicio && $dataInicio <= $fim) ? [$dataInicio] : [];
        }

        $base = new DateTimeImmutable($dataInicio);
        $out  = [];

        if ($rec === 'semanal') {
            $cur = $base;
            if ($cur->format('Y-m-d') < $inicio) {
                $dias = (int)$cur->diff(new DateTimeImmutable($inicio))->days;
                $cur  = $cur->modify('+' . (intdiv($dias, 7) * 7) . ' days');
                if ($cur->format('Y-m-d') < $inicio) $cur = $cur->modify('+7 days');
            }
            while ($cur->format('Y-m-d') <= $fim) {
                $out[] = $cur->format('Y-m-d');
                $cur   = $cur->modify('+7 days');
            }
            return $out;
        }

        // mensal / anual: percorre os meses do período; dia ajustado ao último dia do mês (ex.: 31 → 30/28).
        $passo = $rec === 'anual' ? 12 : 1;
        $m = new DateTimeImmutable(substr($inicio, 0, 7) . '-01');
        $limite = new DateTimeImmutable(substr($fim, 0, 7) . '-01');
        $diaBase = (int)$base->format('d');
        $mesBase = (int)$base->format('n');

        while ($m <= $limite) {
            $ocorre = $rec === 'mensal' || (int)$m->format('n') === $mesBase;
            if ($ocorre) {
                $dia  = min($diaBase, (int)$m->format('t'));
                $data = $m->format('Y-m-') . sprintf('%02d', $dia);
                if ($data >= $dataInicio && $data >= $inicio && $data <= $fim) $out[] = $data;
            }
            $m = $m->modify('+1 month');
        }
        return $out;
    }

    /* ════════════════════════════════════════════
       Vencimentos financeiros (somente leitura)
    ════════════════════════════════════════════ */

    private function vencimentosFinanceiros(string $inicio, string $fim): array
    {
        $itens = [];

        try {
            $pierre = new PierreRepository($this->userId);

            // Meses cobertos pelo período
            $meses = [];
            $m = new DateTimeImmutable(substr($inicio, 0, 7) . '-01');
            $limite = new DateTimeImmutable(substr($fim, 0, 7) . '-01');
            while ($m <= $limite) {
                $meses[] = $m->format('Y-m');
                $m = $m->modify('+1 month');
            }

            foreach ($meses as $mes) {
                foreach ($pierre->getFaturasComoDespesasPrevistas($mes) as $f) {
                    $data = substr((string)$f['primeira_cobranca'], 0, 10);
                    if ($data < $inicio || $data > $fim) continue;
                    $itens[] = $this->itemFinanceiro('fatura', $f['id'], $f['descricao'], $data, (float)$f['valor_parcela']);
                }

                foreach ($pierre->getDespesasPrevistas(true, $mes) as $d) {
                    $primeira = substr((string)$d['primeira_cobranca'], 0, 10);
                    $dia  = (int)substr($primeira, 8, 2);
                    $ult  = (int)(new DateTimeImmutable($mes . '-01'))->format('t');
                    $data = $mes . '-' . sprintf('%02d', min($dia, $ult));
                    if ($data < $inicio || $data > $fim) continue;
                    $itens[] = $this->itemFinanceiro('despesa_prevista', 'dp:' . $d['id'] . ':' . $mes, $d['descricao'], $data, (float)$d['valor_parcela']);
                }
            }

            $emprestimos = new EmprestimosRepository($this->userId);
            foreach ($meses as $mes) {
                foreach ($emprestimos->parcelasDoMes($mes) as $p) {
                    if ($p['data'] < $inicio || $p['data'] > $fim) continue;
                    $itens[] = $this->itemFinanceiro(
                        'emprestimo', 'emp:' . $p['id'] . ':' . $mes,
                        'Empréstimo ' . $p['nome'] . ' (' . $p['parcela'] . '/' . $p['total'] . ')', $p['data'], (float)$p['valor']
                    );
                }
            }

            foreach ($pierre->getAssinaturas(true) as $a) {
                $data = substr((string)($a['proxima_cobranca'] ?? ''), 0, 10);
                if ($data === '' || $data < $inicio || $data > $fim) continue;
                $itens[] = $this->itemFinanceiro('assinatura', 'as:' . $a['id'], $a['descricao'], $data, (float)$a['valor']);
            }
        } catch (\Throwable $e) {
            // Integração financeira indisponível: a agenda segue funcionando só com eventos.
        }

        return $itens;
    }

    private function itemFinanceiro(string $origem, string $id, string $titulo, string $data, float $valor): array
    {
        return [
            'id'          => $id,
            'origem'      => $origem,
            'titulo'      => $titulo,
            'descricao'   => null,
            'tipo'        => 'vencimento',
            'data'        => $data,
            'hora'        => null,
            'recorrencia' => 'nenhuma',
            'concluido'   => false,
            'valor'       => $valor,
        ];
    }
}
