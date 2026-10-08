<?php
/**
 * Motor de sincronização da Agenda com o iCloud (CalDAV), nos dois sentidos.
 *
 * Calendário do app ("Barão Agenda", criado no iCloud):
 *   app -> iPhone : eventos criados/editados/apagados no app são enviados.
 *   iPhone -> app : mudanças feitas nesse calendário no iPhone voltam; eventos novos criados nele são adotados.
 *   Conflito (mudou nos dois lados): vale a versão do iPhone.
 *
 * Demais calendários do iCloud escolhidos: IMPORTAÇÃO somente leitura (aparecem na agenda do app, com
 * repetições já expandidas). Nada neles é alterado ou apagado pelo app.
 */

require_once __DIR__ . '/IcloudCalDav.php';
require_once __DIR__ . '/IcloudStore.php';
require_once __DIR__ . '/../utils/ics.php';
require_once __DIR__ . '/../utils/ics_parse.php';

class IcloudSyncEngine
{
    public const NOME_CALENDARIO_APP = 'Barão Agenda';
    public const NOME_CALENDARIO_VENC = 'Barão Vencimentos';
    /** Máximo de gravações de vencimentos por rodada (mantém a sincronização rápida; o resto segue na próxima). */
    private const LIMITE_VENC_POR_RODADA = 40;
    private const DIAS_VENC_A_FRENTE = 90;
    private const ROTULO_TIPO = ['compromisso' => 'Compromisso', 'lembrete' => 'Lembrete', 'tarefa' => 'Tarefa'];

    private IcloudCalDav $dav;
    private IcloudStore $store;
    private DateTimeImmutable $hoje;

    public function __construct(IcloudCalDav $dav, IcloudStore $store, ?DateTimeImmutable $hoje = null)
    {
        $this->dav   = $dav;
        $this->store = $store;
        $this->hoje  = $hoje ?? new DateTimeImmutable('today', new DateTimeZone(ICS_TZ));
    }

    private static function caminho(string $href): string
    {
        return (string)parse_url($href, PHP_URL_PATH);
    }

    /* ════════════════════════════════════════════
       Descoberta
    ════════════════════════════════════════════ */

    /** Descobre a conta e os calendários, garante o calendário do app e guarda tudo no estado. */
    public function descobrir(bool $criarCalendarioApp): array
    {
        $estado = $this->store->estado();
        $home = $estado['home_url'];
        try {
            if (!$home) throw new RuntimeException('sem home');
            $cals = $this->dav->calendarios($home);
        } catch (IcloudAuthException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $home = $this->dav->descobrir()['home'];   // endereço antigo/ausente: descobre de novo
            $cals = $this->dav->calendarios($home);
        }

        // Dois calendários do app: "Barão Agenda" (eventos, nos dois sentidos) e "Barão Vencimentos" (só do app para o iPhone).
        [$calApp, $achouApp, $cals] = $this->garantirCalendario($home, $cals, $estado['calendario_href'], self::NOME_CALENDARIO_APP, 'barao-agenda', '#7a6cf0', $criarCalendarioApp);
        [$calVenc, $achouVenc, $cals] = $this->garantirCalendario($home, $cals, $estado['calendario_venc_href'] ?? null, self::NOME_CALENDARIO_VENC, 'barao-vencimentos', '#e0605f', $criarCalendarioApp);

        // Mantém a escolha anterior; calendários novos entram selecionados.
        $anteriores = [];
        foreach ($estado['calendarios'] as $c) $anteriores[self::caminho($c['href'])] = $c;
        $lista = [];
        foreach ($cals as $c) {
            $ehApp = ($calApp && self::caminho($c['href']) === self::caminho($calApp))
                  || ($calVenc && self::caminho($c['href']) === self::caminho($calVenc));
            $prev = $anteriores[self::caminho($c['href'])] ?? null;
            $lista[] = ['href' => $c['href'], 'nome' => $c['nome'], 'cor' => $c['cor'], 'app' => $ehApp,
                        'selecionado' => $ehApp ? false : ($prev['selecionado'] ?? true)];
        }

        $this->store->salvarEstado([
            'home_url' => $home, 'calendario_href' => $achouApp ? $calApp : null,
            'calendario_venc_href' => $achouVenc ? $calVenc : null, 'calendarios' => $lista,
        ]);
        return ['home' => $home, 'calendario_app' => $achouApp ? $calApp : null,
                'calendario_venc' => $achouVenc ? $calVenc : null, 'calendarios' => $lista];
    }

    /** Acha o calendário (pelo href guardado ou pelo nome) ou o cria. @return array{0:?string,1:bool,2:array} */
    private function garantirCalendario(string $home, array $cals, ?string $hrefGuardado, string $nome, string $slug, string $cor, bool $criar): array
    {
        foreach ($cals as $c) {
            if (($hrefGuardado && self::caminho($c['href']) === self::caminho($hrefGuardado)) || (!$hrefGuardado && $c['nome'] === $nome)) {
                return [$c['href'], true, $cals];
            }
        }
        if ($criar) {
            $href = $this->dav->criarCalendario($home, $slug, $nome, $cor);
            return [$href, true, $this->dav->calendarios($home)];   // a lista agora inclui o recém-criado
        }
        return [null, false, $cals];
    }

    /* ════════════════════════════════════════════
       Sincronização
    ════════════════════════════════════════════ */

    public function sincronizar(): array
    {
        $r = ['enviados' => 0, 'atualizados_no_iphone' => 0, 'apagados_no_iphone' => 0, 'importados' => 0,
              'adotados' => 0, 'atualizados_no_app' => 0, 'removidos_no_app' => 0,
              'vencimentos_enviados' => 0, 'vencimentos_atualizados' => 0, 'vencimentos_removidos' => 0, 'vencimentos_pendentes' => 0, 'erros' => []];

        $d = $this->descobrir(true);
        $calApp = $d['calendario_app'];
        if (!$calApp) throw new RuntimeException('Não foi possível criar o calendário do app no iCloud.');
        $marcador = $this->store->inicioExecucao();

        $conflitos = $this->enviar($calApp, $r);
        $this->receberCalendarioApp($calApp, $conflitos, $r);
        $this->importarOutros($d['calendarios'], $marcador, $r);
        $this->enviarVencimentos($d['calendario_venc'], $r);

        $this->store->salvarEstado(['ultima_sync' => 'agora', 'ultimo_resultado' => json_encode($r, JSON_UNESCAPED_UNICODE)]);
        return $r;
    }

    /* ── app -> iPhone ── */

    private function montarIcs(array $e, string $uid): string
    {
        return icsObjetoCalDav([
            'uid' => $uid, 'titulo' => $e['titulo'], 'descricao' => $e['descricao'] ?? null,
            'data' => $e['data'], 'hora' => $e['hora'] ?? null, 'recorrencia' => $e['recorrencia'] ?? 'nenhuma',
            'categoria' => self::ROTULO_TIPO[$e['tipo'] ?? 'compromisso'] ?? null,
            'alarme' => icsAlarmePadrao($e['tipo'] ?? 'compromisso', $e['hora'] ?? null),
        ]);
    }

    /** @return array<string,bool> ids em conflito (o iPhone venceu) */
    private function enviar(string $calApp, array &$r): array
    {
        // Eventos apagados no app: apaga no iPhone.
        foreach ($this->store->exclusoesPendentes() as $x) {
            try {
                $st = $this->dav->apagar($x['href'], $x['etag']);
                if ($st === 412) $st = $this->dav->apagar($x['href'], null);   // mudou lá, mas você apagou aqui: vale apagar
                if (in_array($st, [200, 204, 404, 410], true)) {
                    $this->store->removerExclusao($x['href']);
                    $r['apagados_no_iphone']++;
                } else {
                    $r['erros'][] = 'Não foi possível apagar um evento no iPhone (HTTP ' . $st . ').';
                }
            } catch (IcloudAuthException $e) { throw $e; } catch (\Throwable $e) { $r['erros'][] = $e->getMessage(); }
        }

        $conflitos = [];
        foreach ($this->store->eventosApp() as $e) {
            if (!empty($e['icloud_href']) && !$e['sujo']) continue;
            $uid  = $e['icloud_uid'] ?: 'ag-' . $e['id'] . '@baraofinance';
            $ics  = $this->montarIcs($e, $uid);
            try {
                if (empty($e['icloud_href'])) {
                    $href = rtrim($calApp, '/') . '/ag-' . $e['id'] . '.ics';
                    $g = $this->dav->gravar($href, $ics, null);
                    if ($g['status'] === 412) {                                  // já existia (estado perdido): atualiza por cima
                        $et = $this->dav->etag($href);
                        $g = $et ? $this->dav->gravar($href, $ics, $et) : $g;
                    }
                    if (in_array($g['status'], [200, 201, 204], true)) {
                        $this->store->marcarEnviado($e['id'], $uid, $href, $g['etag'], $e['versao'] ?? null);
                        $r['enviados']++;
                    } else {
                        $r['erros'][] = 'Não foi possível enviar "' . $e['titulo'] . '" (HTTP ' . $g['status'] . ').';
                    }
                } else {
                    $g = $this->dav->gravar($e['icloud_href'], $ics, $e['icloud_etag']);
                    if (in_array($g['status'], [200, 201, 204], true)) {
                        $this->store->marcarEnviado($e['id'], $uid, $e['icloud_href'], $g['etag'], $e['versao'] ?? null);
                        $r['atualizados_no_iphone']++;
                    } elseif ($g['status'] === 412) {
                        $conflitos[$e['id']] = true;                              // mudou nos dois lados: vale o iPhone
                    } elseif ($g['status'] === 404) {
                        $this->store->limparLink($e['id']);                       // sumiu lá: recria agora
                        $href = rtrim($calApp, '/') . '/ag-' . $e['id'] . '.ics';
                        $g2 = $this->dav->gravar($href, $ics, null);
                        if (in_array($g2['status'], [200, 201, 204], true)) {
                            $this->store->marcarEnviado($e['id'], $uid, $href, $g2['etag'], $e['versao'] ?? null);
                            $r['enviados']++;
                        }
                    } else {
                        $r['erros'][] = 'Não foi possível atualizar "' . $e['titulo'] . '" (HTTP ' . $g['status'] . ').';
                    }
                }
            } catch (IcloudAuthException $ex) { throw $ex; } catch (\Throwable $ex) { $r['erros'][] = $ex->getMessage(); }
        }
        return $conflitos;
    }

    /* ── iPhone -> app (calendário do app) ── */

    /** Campos do app a partir de um VEVENT; 'recorrencia' só vem quando dá para representar. */
    private function camposDoEvento(array $v): array
    {
        $desc = $v['description'];
        if ($v['location'] !== '') $desc = trim($desc . "\nLocal: " . $v['location']);
        $campos = [
            'titulo' => $v['summary'] !== '' ? $v['summary'] : '(sem título)',
            'descricao' => $desc !== '' ? $desc : null,
            'data' => $v['dtstart']['data'],
            'hora' => $v['dtstart']['hora'],
        ];
        if (empty($v['rrule'])) {
            $campos['recorrencia'] = 'nenhuma';
        } else {
            $p = [];
            foreach (explode(';', $v['rrule']) as $kv) { $x = explode('=', $kv, 2); if (count($x) === 2) $p[strtoupper($x[0])] = $x[1]; }
            $simples = (int)($p['INTERVAL'] ?? 1) === 1 && !isset($p['COUNT']) && !isset($p['UNTIL']) && substr_count($p['BYDAY'] ?? '', ',') === 0;
            $mapa = ['WEEKLY' => 'semanal', 'MONTHLY' => 'mensal', 'YEARLY' => 'anual'];
            if ($simples && isset($mapa[strtoupper($p['FREQ'] ?? '')])) $campos['recorrencia'] = $mapa[strtoupper($p['FREQ'])];
            // senão: repetição que o app não representa — mantém a que já existe
        }
        return $campos;
    }

    private function receberCalendarioApp(string $calApp, array $conflitos, array &$r): void
    {
        $remotos = $this->dav->eventos($calApp);
        $locais = $this->store->eventosApp();
        $porId = $porUid = [];
        foreach ($locais as $l) {
            $porId[$l['id']] = $l;
            if (!empty($l['icloud_uid'])) $porUid[$l['icloud_uid']] = $l;
        }

        $vistos = [];
        foreach ($remotos as $it) {
            $evs = array_values(array_filter(icsParseEventos($it['ics']), static fn($v) => empty($v['recurrence_id'])));
            if (!$evs) continue;
            $v = $evs[0];
            $vistos[self::caminho($it['href'])] = true;

            $id = preg_match('/^ag-([a-f0-9-]{36})@baraofinance$/i', $v['uid'], $m) ? strtolower($m[1]) : null;
            $local = ($id !== null && isset($porId[$id])) ? $porId[$id] : ($porUid[$v['uid']] ?? null);

            if ($local !== null) {
                $mudouLa = ($local['icloud_etag'] ?? null) !== $it['etag'];
                if ($mudouLa && (!$local['sujo'] || isset($conflitos[$local['id']]))) {
                    $this->store->aplicarRemotoApp($local['id'], $this->camposDoEvento($v), $it['href'], $it['etag']);
                    $r['atualizados_no_app']++;
                } elseif (empty($local['icloud_href'])) {
                    $this->store->marcarEnviado($local['id'], $v['uid'], $it['href'], $it['etag'], $local['versao'] ?? null);
                }
            } elseif ($id === null) {
                $this->store->adotarRemoto($this->camposDoEvento($v), $v['uid'], $it['href'], $it['etag']);   // criado no iPhone neste calendário
                $r['adotados']++;
            }
            // UID do app sem linha local: foi apagado no app; a exclusão pendente cuida do iPhone.
        }

        // Apagados no iPhone: somem do app (se não estiver editado aqui, para não perder trabalho).
        foreach ($this->store->eventosApp() as $l) {
            if (empty($l['icloud_href']) || isset($vistos[self::caminho($l['icloud_href'])])) continue;
            if ($l['sujo'] && !isset($conflitos[$l['id']])) {
                $this->store->limparLink($l['id']);
            } else {
                $this->store->excluirLocal($l['id']);
                $r['removidos_no_app']++;
            }
        }
    }

    /* ── app -> iPhone: vencimentos (calendário "Barão Vencimentos", só leitura no iPhone) ── */

    private function enviarVencimentos(?string $calVenc, array &$r): void
    {
        if (!$calVenc) return;

        $ini = $this->hoje->modify('-7 days')->format('Y-m-d');
        $fim = $this->hoje->modify('+' . self::DIAS_VENC_A_FRENTE . ' days')->format('Y-m-d');
        $rotulo = ['assinatura' => 'Assinatura ', 'despesa_prevista' => 'Despesa: '];

        $desejados = [];
        foreach ($this->store->vencimentos($ini, $fim) as $v) {
            $uid = 'fin-' . md5($v['origem'] . '|' . $v['id'] . '|' . $v['data']) . '@baraofinance';
            // Sem valores em reais no título: o calendário do iPhone fica visível em widgets e na tela de bloqueio.
            $desejados[$uid] = ['uid' => $uid, 'titulo' => 'Vence: ' . ($rotulo[$v['origem']] ?? '') . $v['titulo'],
                                'data' => $v['data'], 'categoria' => 'Vencimento', 'alarme' => 'antes:9'];
        }

        try {
            $remotos = [];
            foreach ($this->dav->eventos($calVenc) as $it) {
                $evs = icsParseEventos($it['ics']);
                if (!$evs) continue;
                $remotos[$evs[0]['uid']] = ['href' => $it['href'], 'etag' => $it['etag'], 'titulo' => $evs[0]['summary'], 'data' => $evs[0]['dtstart']['data']];
            }
        } catch (IcloudAuthException $e) { throw $e; } catch (\Throwable $e) {
            $r['erros'][] = 'Vencimentos: ' . $e->getMessage();
            return;
        }

        $gravacoes = 0;
        foreach ($desejados as $uid => $e) {
            $rem = $remotos[$uid] ?? null;
            if ($rem && $rem['titulo'] === $e['titulo'] && $rem['data'] === $e['data']) continue;   // já está igual
            if ($gravacoes >= self::LIMITE_VENC_POR_RODADA) { $r['vencimentos_pendentes']++; continue; }

            $href = $rem['href'] ?? rtrim($calVenc, '/') . '/' . substr($uid, 0, strpos($uid, '@')) . '.ics';
            try {
                $g = $this->dav->gravar($href, icsObjetoCalDav($e), $rem['etag'] ?? null);
                if ($g['status'] === 412) {                                  // existia/mudou: atualiza por cima
                    $et = $this->dav->etag($href);
                    $g = $et ? $this->dav->gravar($href, icsObjetoCalDav($e), $et) : $g;
                }
                if (in_array($g['status'], [200, 201, 204], true)) {
                    $rem ? $r['vencimentos_atualizados']++ : $r['vencimentos_enviados']++;
                    $gravacoes++;
                } else {
                    $r['erros'][] = 'Vencimento "' . $e['titulo'] . '" (HTTP ' . $g['status'] . ').';
                }
            } catch (IcloudAuthException $ex) { throw $ex; } catch (\Throwable $ex) { $r['erros'][] = $ex->getMessage(); }
        }

        // Vencimentos que deixaram de existir (fatura paga, contrato quitado…): remove só dentro da janela; o passado fica.
        foreach ($remotos as $uid => $rem) {
            if (isset($desejados[$uid]) || substr($uid, 0, 4) !== 'fin-' || $rem['data'] < $ini || $rem['data'] > $fim) continue;
            if ($gravacoes >= self::LIMITE_VENC_POR_RODADA) { $r['vencimentos_pendentes']++; continue; }
            try {
                $st = $this->dav->apagar($rem['href'], $rem['etag']);
                if ($st === 412) $st = $this->dav->apagar($rem['href'], null);
                if (in_array($st, [200, 204, 404, 410], true)) { $r['vencimentos_removidos']++; $gravacoes++; }
            } catch (IcloudAuthException $ex) { throw $ex; } catch (\Throwable $ex) { $r['erros'][] = $ex->getMessage(); }
        }
    }

    /* ── iPhone -> app (outros calendários, somente leitura) ── */

    private function importarOutros(array $calendarios, string $marcador, array &$r): void
    {
        $tz = new DateTimeZone(ICS_TZ);
        $ini = $this->hoje->modify('-30 days');
        $fim = $this->hoje->modify('+365 days');
        $iniUtc = $ini->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $fimUtc = $fim->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $selecionados = [];

        foreach ($calendarios as $cal) {
            if (!empty($cal['app']) || empty($cal['selecionado'])) continue;
            $selecionados[] = $cal['href'];
            try {
                foreach ($this->dav->eventos($cal['href'], $iniUtc, $fimUtc, true) as $it) {
                    foreach (icsParseEventos($it['ics']) as $v) {
                        if ($v['status'] === 'CANCELLED' || substr($v['uid'], -13) === '@baraofinance') continue;   // o que o app mesmo exportou não volta

                        // O servidor deveria expandir; se vier o mestre com RRULE, expandimos aqui.
                        $inicios = (!empty($v['rrule']) && empty($v['recurrence_id']))
                            ? icsExpandirRrule($v, $ini, $fim->setTime(23, 59, 59))
                            : [$v['dtstart']['dt']];

                        $multidia = '';
                        if (!empty($v['dtend']) && $v['dtstart']['dia_inteiro']) {
                            $ultimoDia = $v['dtend']['dt']->modify('-1 day');
                            if ($ultimoDia > $v['dtstart']['dt']) $multidia = 'Até ' . $ultimoDia->format('d/m/Y');
                        }

                        foreach ($inicios as $dt) {
                            $dt = $dt->setTimezone($tz);
                            $data = $dt->format('Y-m-d');
                            if ($data < $ini->format('Y-m-d') || $data > $fim->format('Y-m-d')) continue;
                            $hora = $v['dtstart']['dia_inteiro'] ? null : $dt->format('H:i');

                            $desc = $v['description'];
                            if ($v['location'] !== '') $desc = trim($desc . "\nLocal: " . $v['location']);
                            if ($multidia !== '') $desc = trim($desc . "\n" . $multidia);

                            $chaveInst = !empty($v['recurrence_id'])
                                ? $v['recurrence_id']['data'] . 'T' . ($v['recurrence_id']['hora'] ?? '00:00')
                                : $data . 'T' . ($hora ?? '00:00');
                            $this->store->upsertImportado(
                                ['titulo' => $v['summary'] !== '' ? $v['summary'] : '(sem título)', 'descricao' => $desc !== '' ? $desc : null,
                                 'data' => $data, 'hora' => $hora],
                                $v['uid'] . '|' . $chaveInst, $cal['href'], $cal['nome']
                            );
                            $r['importados']++;
                        }
                    }
                }
                $r['removidos_no_app'] += $this->store->removerImportadosAntigos($cal['href'], $marcador, $ini->format('Y-m-d'), $fim->format('Y-m-d'));
            } catch (IcloudAuthException $e) { throw $e; } catch (\Throwable $e) {
                $r['erros'][] = 'Calendário "' . $cal['nome'] . '": ' . $e->getMessage();
            }
        }
        $r['removidos_no_app'] += $this->store->removerImportadosForaDe($selecionados);
    }
}
