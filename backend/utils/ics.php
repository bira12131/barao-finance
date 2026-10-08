<?php
/**
 * Geração de calendário iCalendar (.ics, RFC 5545) para o iPhone assinar.
 * Horários vão em UTC (o Brasil não tem horário de verão desde 2019), o que evita depender de VTIMEZONE.
 */

function icsEscape(string $t): string
{
    return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\;', '\\,', '\\n', '\\n', '\\n'], $t);
}

/** Quebra linhas longas em 75 bytes sem cortar caracteres UTF-8. */
function icsFold(string $linha): string
{
    $out = '';
    $primeira = true;
    while (strlen($linha) > 0) {
        $limite = $primeira ? 75 : 74;                // linhas de continuação começam com um espaço
        $parte = mb_strcut($linha, 0, $limite, 'UTF-8');
        $out .= ($primeira ? '' : ' ') . $parte . "\r\n";
        $linha = substr($linha, strlen($parte));
        $primeira = false;
    }
    return $out;
}

function icsUtc(string $data, string $hora): string
{
    $dt = new DateTimeImmutable($data . ' ' . $hora . ':00', new DateTimeZone('America/Sao_Paulo'));
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

/**
 * Monta um VEVENT.
 * $e: uid, titulo, descricao?, data (Y-m-d), hora? (HH:MM), recorrencia? (nenhuma|semanal|mensal|anual),
 *     categoria?, alarme? ('min:30' = 30 min antes; 'dia:9' = 9h do dia; 'antes:9' = 9h do dia anterior)
 */
function icsEvento(array $e, string $dtstamp): string
{
    $linhas = ['BEGIN:VEVENT', 'UID:' . $e['uid'], 'DTSTAMP:' . $dtstamp];

    if (!empty($e['hora'])) {
        $linhas[] = 'DTSTART:' . icsUtc($e['data'], $e['hora']);
        $fim = (new DateTimeImmutable($e['data'] . ' ' . $e['hora'] . ':00', new DateTimeZone('America/Sao_Paulo')))->modify('+1 hour');
        $linhas[] = 'DTEND:' . $fim->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    } else {
        $d = new DateTimeImmutable($e['data']);
        $linhas[] = 'DTSTART;VALUE=DATE:' . $d->format('Ymd');
        $linhas[] = 'DTEND;VALUE=DATE:' . $d->modify('+1 day')->format('Ymd');
    }

    $rec = $e['recorrencia'] ?? 'nenhuma';
    if ($rec === 'semanal') {
        $linhas[] = 'RRULE:FREQ=WEEKLY';
    } elseif ($rec === 'mensal') {
        // Dia 29-31: "último dia do mês" (igual à agenda do app, que ajusta ao fim de meses curtos).
        $linhas[] = ((int)substr($e['data'], 8, 2) >= 29) ? 'RRULE:FREQ=MONTHLY;BYMONTHDAY=-1' : 'RRULE:FREQ=MONTHLY';
    } elseif ($rec === 'anual') {
        $linhas[] = 'RRULE:FREQ=YEARLY';
    }

    $linhas[] = 'SUMMARY:' . icsEscape($e['titulo']);
    if (!empty($e['descricao'])) $linhas[] = 'DESCRIPTION:' . icsEscape($e['descricao']);
    if (!empty($e['categoria'])) $linhas[] = 'CATEGORIES:' . icsEscape($e['categoria']);
    $linhas[] = 'TRANSP:TRANSPARENT';

    if (!empty($e['alarme'])) {
        [$tipo, $n] = explode(':', $e['alarme']) + [1 => '0'];
        $gatilho = $tipo === 'min'   ? '-PT' . (int)$n . 'M'
                 : ($tipo === 'antes' ? '-PT' . (24 - (int)$n) . 'H' : 'PT' . (int)$n . 'H');
        array_push($linhas, 'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:' . icsEscape($e['titulo']),
            'TRIGGER;RELATED=START:' . $gatilho, 'END:VALARM');
    }

    $linhas[] = 'END:VEVENT';
    return implode("\r\n", array_map(static fn($l) => rtrim(icsFold($l), "\r\n"), $linhas)) . "\r\n";
}

/** Alarme padrão por tipo: lembrete avisa na hora (ou 9h se for dia inteiro); compromisso com hora avisa 30 min antes. */
function icsAlarmePadrao(string $tipo, ?string $hora): ?string
{
    if ($tipo === 'lembrete') return empty($hora) ? 'dia:9' : 'min:0';
    return !empty($hora) ? 'min:30' : null;
}

/** Um único evento embrulhado em VCALENDAR (formato aceito por PUT no CalDAV). */
function icsObjetoCalDav(array $e): string
{
    $cab = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Barao Finance//Agenda//PT-BR', 'CALSCALE:GREGORIAN'];
    return implode("\r\n", $cab) . "\r\n" . icsEvento($e, gmdate('Ymd\THis\Z')) . "END:VCALENDAR\r\n";
}

function icsCalendario(string $nome, array $eventos): string
{
    $dtstamp = gmdate('Ymd\THis\Z');
    $cab = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Barao Finance//Agenda//PT-BR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
        'X-WR-CALNAME:' . icsEscape($nome), 'X-WR-TIMEZONE:America/Sao_Paulo',
        'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H',
    ];
    $out = implode("\r\n", array_map(static fn($l) => rtrim(icsFold($l), "\r\n"), $cab)) . "\r\n";
    foreach ($eventos as $e) $out .= icsEvento($e, $dtstamp);
    return $out . "END:VCALENDAR\r\n";
}
