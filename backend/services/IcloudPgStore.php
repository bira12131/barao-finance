<?php
/**
 * Armazenamento PostgreSQL da sincronização com o iCloud (tabelas criadas por AgendaRepository).
 */

require_once __DIR__ . '/IcloudStore.php';
require_once __DIR__ . '/../repositories/AgendaRepository.php';

class IcloudPgStore implements IcloudStore
{
    /** @var PDO */
    private $pdo;
    private string $userId;

    /** @param PDO $pdo */
    public function __construct($pdo, string $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }

    public function estado(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT CASE WHEN habilitado THEN 1 ELSE 0 END AS habilitado, calendario_href, calendario_venc_href, home_url, calendarios::text AS calendarios,
                    ultima_sync::text AS ultima_sync, ultimo_resultado
             FROM icloud_sync WHERE user_id = :u"
        );
        $stmt->execute([':u' => $this->userId]);
        $r = $stmt->fetch();
        if (!$r) {
            return ['habilitado' => false, 'calendario_href' => null, 'calendario_venc_href' => null, 'home_url' => null, 'calendarios' => [], 'ultima_sync' => null, 'ultimo_resultado' => null];
        }
        return [
            'habilitado'       => (int)$r['habilitado'] === 1,
            'calendario_href'  => $r['calendario_href'],
            'calendario_venc_href' => $r['calendario_venc_href'],
            'home_url'         => $r['home_url'],
            'calendarios'      => json_decode($r['calendarios'] ?: '[]', true) ?: [],
            'ultima_sync'      => $r['ultima_sync'],
            'ultimo_resultado' => $r['ultimo_resultado'],
        ];
    }

    public function salvarEstado(array $parcial): void
    {
        $this->pdo->prepare('INSERT INTO icloud_sync (user_id) VALUES (:u) ON CONFLICT (user_id) DO NOTHING')
            ->execute([':u' => $this->userId]);

        $sets = ['atualizado_em = NOW()'];
        $params = [':u' => $this->userId];
        foreach ($parcial as $campo => $valor) {
            switch ($campo) {
                case 'habilitado':
                    $sets[] = 'habilitado = :habilitado';
                    $params[':habilitado'] = $valor ? 'true' : 'false';
                    break;
                case 'calendario_href':
                case 'calendario_venc_href':
                case 'home_url':
                case 'ultimo_resultado':
                    $sets[] = "$campo = :$campo";
                    $params[":$campo"] = $valor;
                    break;
                case 'calendarios':
                    $sets[] = 'calendarios = :calendarios::jsonb';
                    $params[':calendarios'] = json_encode($valor, JSON_UNESCAPED_UNICODE);
                    break;
                case 'ultima_sync':
                    $sets[] = 'ultima_sync = NOW()';   // o valor ('agora') só sinaliza "carimbar"
                    break;
            }
        }
        $this->pdo->prepare('UPDATE icloud_sync SET ' . implode(', ', $sets) . ' WHERE user_id = :u')->execute($params);
    }

    public function inicioExecucao(): string
    {
        return (string)$this->pdo->query('SELECT clock_timestamp()::text')->fetchColumn();
    }

    public function eventosApp(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, titulo, descricao, tipo, data_inicio::text AS data, TO_CHAR(hora, 'HH24:MI') AS hora, recorrencia,
                    icloud_uid, icloud_href, icloud_etag, atualizado_em::text AS versao,
                    CASE WHEN icloud_sync_em IS NULL OR atualizado_em > icloud_sync_em THEN 1 ELSE 0 END AS sujo
             FROM agenda_eventos WHERE user_id = :u AND origem = 'app'"
        );
        $stmt->execute([':u' => $this->userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) $r['sujo'] = (int)$r['sujo'] === 1;
        unset($r);
        return $rows;
    }

    public function marcarEnviado(string $id, string $uid, string $href, ?string $etag, ?string $versao = null): void
    {
        $this->pdo->prepare(
            "UPDATE agenda_eventos
             SET icloud_uid = :uid, icloud_href = :h, icloud_etag = :e, icloud_sync_em = COALESCE(:v::timestamptz, NOW())
             WHERE id = :id AND user_id = :u AND origem = 'app'"
        )->execute([':uid' => $uid, ':h' => $href, ':e' => $etag, ':v' => $versao, ':id' => $id, ':u' => $this->userId]);
    }

    public function limparLink(string $id): void
    {
        $this->pdo->prepare(
            "UPDATE agenda_eventos SET icloud_href = NULL, icloud_etag = NULL, icloud_sync_em = NULL
             WHERE id = :id AND user_id = :u AND origem = 'app'"
        )->execute([':id' => $id, ':u' => $this->userId]);
    }

    public function exclusoesPendentes(): array
    {
        $stmt = $this->pdo->prepare('SELECT href, etag FROM agenda_icloud_exclusoes WHERE user_id = :u');
        $stmt->execute([':u' => $this->userId]);
        return $stmt->fetchAll();
    }

    public function removerExclusao(string $href): void
    {
        $this->pdo->prepare('DELETE FROM agenda_icloud_exclusoes WHERE user_id = :u AND href = :h')
            ->execute([':u' => $this->userId, ':h' => $href]);
    }

    public function aplicarRemotoApp(string $id, array $campos, string $href, ?string $etag): void
    {
        $this->pdo->prepare(
            "UPDATE agenda_eventos
             SET titulo = :titulo, descricao = :descricao, data_inicio = :data, hora = :hora,
                 recorrencia = COALESCE(:rec, recorrencia), icloud_href = :h, icloud_etag = :e,
                 atualizado_em = NOW(), icloud_sync_em = NOW()
             WHERE id = :id AND user_id = :u AND origem = 'app'"
        )->execute([
            ':titulo' => $campos['titulo'], ':descricao' => $campos['descricao'] ?? null, ':data' => $campos['data'],
            ':hora' => $campos['hora'] ?? null, ':rec' => $campos['recorrencia'] ?? null,
            ':h' => $href, ':e' => $etag, ':id' => $id, ':u' => $this->userId,
        ]);
    }

    public function adotarRemoto(array $campos, string $uid, string $href, ?string $etag): void
    {
        $this->pdo->prepare(
            "INSERT INTO agenda_eventos (user_id, titulo, descricao, tipo, data_inicio, hora, recorrencia, origem,
                                         icloud_uid, icloud_href, icloud_etag, icloud_sync_em)
             VALUES (:u, :titulo, :descricao, 'compromisso', :data, :hora, COALESCE(:rec, 'nenhuma'), 'app', :uid, :h, :e, NOW())
             ON CONFLICT (user_id, icloud_uid) WHERE icloud_uid IS NOT NULL DO NOTHING"
        )->execute([
            ':u' => $this->userId, ':titulo' => $campos['titulo'], ':descricao' => $campos['descricao'] ?? null,
            ':data' => $campos['data'], ':hora' => $campos['hora'] ?? null, ':rec' => $campos['recorrencia'] ?? null,
            ':uid' => $uid, ':h' => $href, ':e' => $etag,
        ]);
    }

    public function excluirLocal(string $id): void
    {
        $this->pdo->prepare("DELETE FROM agenda_eventos WHERE id = :id AND user_id = :u AND origem = 'app'")
            ->execute([':id' => $id, ':u' => $this->userId]);
    }

    public function upsertImportado(array $campos, string $uidChave, string $calHref, string $calNome): void
    {
        $this->pdo->prepare(
            "INSERT INTO agenda_eventos (user_id, titulo, descricao, tipo, data_inicio, hora, recorrencia, origem,
                                         icloud_uid, icloud_cal_href, icloud_cal_nome, icloud_sync_em)
             VALUES (:u, :titulo, :descricao, 'compromisso', :data, :hora, 'nenhuma', 'icloud', :uid, :cal, :nome, NOW())
             ON CONFLICT (user_id, icloud_uid) WHERE icloud_uid IS NOT NULL DO UPDATE SET
                titulo = EXCLUDED.titulo, descricao = EXCLUDED.descricao, data_inicio = EXCLUDED.data_inicio,
                hora = EXCLUDED.hora, icloud_cal_href = EXCLUDED.icloud_cal_href, icloud_cal_nome = EXCLUDED.icloud_cal_nome,
                icloud_sync_em = NOW()
             WHERE agenda_eventos.origem = 'icloud'"
        )->execute([
            ':u' => $this->userId, ':titulo' => $campos['titulo'], ':descricao' => $campos['descricao'] ?? null,
            ':data' => $campos['data'], ':hora' => $campos['hora'] ?? null, ':uid' => $uidChave, ':cal' => $calHref, ':nome' => $calNome,
        ]);
    }

    public function removerImportadosAntigos(string $calHref, string $marcador, string $janelaIni, string $janelaFim): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM agenda_eventos
             WHERE user_id = :u AND origem = 'icloud' AND icloud_cal_href = :c
               AND data_inicio BETWEEN :i AND :f AND icloud_sync_em < :m::timestamptz"
        );
        $stmt->execute([':u' => $this->userId, ':c' => $calHref, ':i' => $janelaIni, ':f' => $janelaFim, ':m' => $marcador]);
        return $stmt->rowCount();
    }

    public function vencimentos(string $inicio, string $fim): array
    {
        $out = [];
        foreach ((new AgendaRepository($this->userId))->vencimentosEntre($inicio, $fim) as $v) {
            $out[] = ['origem' => $v['origem'], 'id' => (string)$v['id'], 'titulo' => $v['titulo'], 'data' => $v['data']];
        }
        return $out;
    }

    public function removerImportadosForaDe(array $calHrefs): int
    {
        if (!$calHrefs) {
            $stmt = $this->pdo->prepare("DELETE FROM agenda_eventos WHERE user_id = :u AND origem = 'icloud'");
            $stmt->execute([':u' => $this->userId]);
            return $stmt->rowCount();
        }
        $marcas = [];
        $params = [':u' => $this->userId];
        foreach (array_values($calHrefs) as $i => $h) {
            $marcas[] = ':h' . $i;
            $params[':h' . $i] = $h;
        }
        $stmt = $this->pdo->prepare(
            "DELETE FROM agenda_eventos
             WHERE user_id = :u AND origem = 'icloud' AND (icloud_cal_href IS NULL OR icloud_cal_href NOT IN (" . implode(',', $marcas) . '))'
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
