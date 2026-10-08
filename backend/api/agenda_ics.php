<?php
/**
 * ENDPOINT PÚBLICO — /backend/api/agenda_ics.php?t=<token>
 *
 * Calendário .ics que o iPhone assina (Ajustes > Calendário > Contas > Adicionar Calendário Assinado).
 * O iPhone não envia cookie de login, então o acesso é por um token secreto e revogável de 192 bits.
 * Só leitura: compromissos, lembretes e tarefas da agenda + vencimentos (sem valores em reais).
 */

require_once __DIR__ . '/../utils/response.php';   // define o fuso de Brasília
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/AgendaRepository.php';
require_once __DIR__ . '/../utils/ics.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit;
}

$token = (string)($_GET['t'] ?? '');
$userId = AgendaRepository::usuarioPorTokenIcs(getConnection(), $token);
if ($userId === null) {
    http_response_code(404);   // não revela se o token existiu
    exit;
}

try {
    $repo = new AgendaRepository($userId);
    $eventos = [];

    $rotuloTipo = ['compromisso' => 'Compromisso', 'lembrete' => 'Lembrete', 'tarefa' => 'Tarefa'];
    // Com o iCloud ligado, os eventos do app já chegam pelo calendário "Barão Agenda": o feed leva só vencimentos.
    $eventosApp = $repo->icloudHabilitado() ? [] : $repo->eventosBrutos();
    foreach ($eventosApp as $r) {
        $concluida = $r['recorrencia'] === 'nenhuma' && $r['concluido'];
        $alarme = icsAlarmePadrao($r['tipo'], $r['hora']);
        $eventos[] = [
            'uid'         => 'ag-' . $r['id'] . '@baraofinance',
            'titulo'      => ($concluida ? '✓ ' : '') . $r['titulo'],
            'descricao'   => $r['descricao'],
            'data'        => $r['data_inicio'],
            'hora'        => $r['hora'],
            'recorrencia' => $r['recorrencia'],
            'categoria'   => $rotuloTipo[$r['tipo']] ?? null,
            'alarme'      => $alarme,
        ];
    }

    // Com o iCloud ligado os vencimentos também já chegam pelo calendário "Barão Vencimentos": o feed fica vazio.
    $icloud = $repo->icloudHabilitado();

    // Vencimentos dos próximos meses, como eventos de dia inteiro (alarme às 9h do dia anterior).
    $rotuloOrigem = ['fatura' => '', 'assinatura' => 'Assinatura ', 'emprestimo' => '', 'despesa_prevista' => 'Despesa: '];
    $inicio = date('Y-m-d', strtotime('-7 days'));
    $fim    = date('Y-m-d', strtotime('+180 days'));
    foreach ($icloud ? [] : $repo->vencimentosEntre($inicio, $fim) as $v) {
        $eventos[] = [
            'uid'       => 'fin-' . md5($v['origem'] . '|' . $v['id'] . '|' . $v['data']) . '@baraofinance',
            'titulo'    => 'Vence: ' . ($rotuloOrigem[$v['origem']] ?? '') . $v['titulo'],
            'data'      => $v['data'],
            'categoria' => 'Vencimento',
            'alarme'    => 'antes:9',
        ];
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="barao-finance.ics"');
    header('Cache-Control: private, max-age=900');
    echo icsCalendario('Barão Finance', $eventos);
} catch (\Throwable $e) {
    http_response_code(500);
}
