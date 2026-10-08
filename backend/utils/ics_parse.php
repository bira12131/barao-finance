<?php
/**
 * Leitura de calendário iCalendar (.ics): VEVENT -> dados simples em horário de Brasília.
 * Usado pela sincronização com o iCloud (CalDAV). Ignora alarmes, tarefas e fusos exóticos (usa Brasília).
 */

const ICS_TZ = 'America/Sao_Paulo';

/** Junta linhas dobradas (RFC 5545 §3.1). */
function icsDesdobrar(string $ics): array
{
    $ics = str_replace(["\r\n", "\r"], "\n", $ics);
    $out = [];
    foreach (explode("\n", $ics) as $l) {
        if ($l === '') continue;
        if (($l[0] === ' ' || $l[0] === "\t") && $out) {
            $out[count($out) - 1] .= substr($l, 1);
        } else {
            $out[] = $l;
        }
    }
    return $out;
}

/** "NOME;P1=V1;P2=V2:valor" -> [NOME, [P1=>V1,...], valor]. */
function icsParseLinha(string $l): ?array
{
    $dentro = false;
    $pos = -1;
    $n = strlen($l);
    for ($i = 0; $i < $n; $i++) {
        $c = $l[$i];
        if ($c === '"') $dentro = !$dentro;
        elseif ($c === ':' && !$dentro) { $pos = $i; break; }
    }
    if ($pos < 0) return null;

    $cabeca = substr($l, 0, $pos);
    $valor  = substr($l, $pos + 1);
    $partes = explode(';', $cabeca);
    $nome   = strtoupper(trim(array_shift($partes)));
    $params = [];
    foreach ($partes as $p) {
        $kv = explode('=', $p, 2);
        if (count($kv) === 2) $params[strtoupper(trim($kv[0]))] = trim($kv[1], " \"");
    }
    return [$nome, $params, $valor];
}

function icsDesescapar(string $t): string
{
    return strtr($t, ['\\n' => "\n", '\\N' => "\n", '\\,' => ',', '\;' => ';', '\\\\' => '\\']);
}

/** Valor de data/hora -> ['data','hora','dia_inteiro','dt'] em horário de Brasília. */
function icsParseData(string $valor, array $params = []): ?array
{
    $tz = new DateTimeZone(ICS_TZ);
    $valor = trim($valor);

    if (($params['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $valor)) {
        $d = DateTimeImmutable::createFromFormat('!Ymd', substr($valor, 0, 8), $tz);
        if (!$d) return null;
        return ['data' => $d->format('Y-m-d'), 'hora' => null, 'dia_inteiro' => true, 'dt' => $d];
    }

    if (preg_match('/^(\d{8})T(\d{6})(Z?)$/', $valor, $m)) {
        $base = $m[1] . 'T' . $m[2];
        if ($m[3] === 'Z') {
            $dt = DateTimeImmutable::createFromFormat('Ymd\THis', $base, new DateTimeZone('UTC'));
        } else {
            $zona = $tz;
            if (!empty($params['TZID'])) {
                try { $zona = new DateTimeZone($params['TZID']); } catch (\Throwable $e) { $zona = $tz; }
            }
            $dt = DateTimeImmutable::createFromFormat('Ymd\THis', $base, $zona);
        }
        if (!$dt) return null;
        $dt = $dt->setTimezone($tz);
        return ['data' => $dt->format('Y-m-d'), 'hora' => $dt->format('H:i'), 'dia_inteiro' => false, 'dt' => $dt];
    }
    return null;
}

/** Lista de VEVENT de um .ics (cada item é uma ocorrência ou o evento mestre com RRULE). */
function icsParseEventos(string $ics): array
{
    $eventos = [];
    $atual = null;
    $alarme = 0;

    foreach (icsDesdobrar($ics) as $linha) {
        $u = strtoupper($linha);
        if ($u === 'BEGIN:VEVENT') {
            $atual = ['uid' => null, 'summary' => '', 'description' => '', 'location' => '', 'dtstart' => null,
                      'dtend' => null, 'rrule' => null, 'recurrence_id' => null, 'status' => '', 'exdates' => []];
            $alarme = 0;
            continue;
        }
        if ($u === 'END:VEVENT') {
            if ($atual !== null && $atual['dtstart'] !== null && $atual['uid'] !== null) $eventos[] = $atual;
            $atual = null;
            continue;
        }
        if ($atual === null) continue;
        if ($u === 'BEGIN:VALARM') { $alarme++; continue; }
        if ($u === 'END:VALARM')   { $alarme--; continue; }
        if ($alarme > 0) continue;

        $p = icsParseLinha($linha);
        if ($p === null) continue;
        [$nome, $params, $valor] = $p;
        switch ($nome) {
            case 'UID':           $atual['uid'] = trim($valor); break;
            case 'SUMMARY':       $atual['summary'] = trim(icsDesescapar($valor)); break;
            case 'DESCRIPTION':   $atual['description'] = trim(icsDesescapar($valor)); break;
            case 'LOCATION':      $atual['location'] = trim(icsDesescapar($valor)); break;
            case 'STATUS':        $atual['status'] = strtoupper(trim($valor)); break;
            case 'RRULE':         $atual['rrule'] = trim($valor); break;
            case 'DTSTART':       $atual['dtstart'] = icsParseData($valor, $params); break;
            case 'DTEND':         $atual['dtend'] = icsParseData($valor, $params); break;
            case 'RECURRENCE-ID': $atual['recurrence_id'] = icsParseData($valor, $params); break;
            case 'EXDATE':
                foreach (explode(',', $valor) as $v) {
                    $d = icsParseData($v, $params);
                    if ($d) $atual['exdates'][] = $d['data'];
                }
                break;
        }
    }
    return $eventos;
}

/**
 * Expansão simples de RRULE dentro de [$ini, $fim] (fallback quando o servidor não expande).
 * Cobre FREQ=DAILY|WEEKLY(BYDAY)|MONTHLY(BYMONTHDAY)|YEARLY com INTERVAL, COUNT, UNTIL e EXDATE.
 * Retorna as datas de início (DateTimeImmutable em Brasília) das ocorrências.
 */
function icsExpandirRrule(array $ev, DateTimeImmutable $ini, DateTimeImmutable $fim): array
{
    if (empty($ev['rrule']) || empty($ev['dtstart'])) return [];

    $r = [];
    foreach (explode(';', $ev['rrule']) as $kv) {
        $x = explode('=', $kv, 2);
        if (count($x) === 2) $r[strtoupper($x[0])] = $x[1];
    }
    $freq      = strtoupper($r['FREQ'] ?? '');
    $intervalo = max(1, (int)($r['INTERVAL'] ?? 1));
    $limiteN   = isset($r['COUNT']) ? (int)$r['COUNT'] : null;
    $ate       = null;
    if (!empty($r['UNTIL'])) {
        $u = icsParseData($r['UNTIL']);
        $ate = $u ? $u['dt']->setTime(23, 59, 59) : null;
    }

    $inicio = $ev['dtstart']['dt'];
    $hora   = $inicio->format('H:i:s');
    $fimJanela = $ate !== null && $ate < $fim ? $ate : $fim;
    $mapaDia = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
    $out = [];
    $conta = 0;
    $seguranca = 0;

    $aceita = function (DateTimeImmutable $d) use (&$out, &$conta, $ini, $fimJanela, $ev, $limiteN): bool {
        // devolve false quando já passou do limite (para parar)
        $conta++;
        if ($limiteN !== null && $conta > $limiteN) return false;
        if ($d >= $ini && $d <= $fimJanela && !in_array($d->format('Y-m-d'), $ev['exdates'], true)) $out[] = $d;
        return true;
    };

    if ($freq === 'DAILY') {
        for ($d = $inicio; $d <= $fimJanela && $seguranca++ < 5000; $d = $d->modify('+' . $intervalo . ' days')) {
            if (!$aceita($d)) break;
        }
    } elseif ($freq === 'WEEKLY') {
        $dias = [];
        foreach (explode(',', $r['BYDAY'] ?? '') as $b) {
            $b = strtoupper(substr(trim($b), -2));
            if (isset($mapaDia[$b])) $dias[] = $mapaDia[$b];
        }
        if (!$dias) $dias = [(int)$inicio->format('w')];
        $inicioSemana = $inicio->modify('-' . (int)$inicio->format('w') . ' days')->setTime(0, 0);
        for ($d = $inicio->setTime(0, 0); $d <= $fimJanela && $seguranca++ < 5000; $d = $d->modify('+1 day')) {
            if ($d < $inicio->setTime(0, 0)) continue;
            $semanas = intdiv((int)$inicioSemana->diff($d)->days, 7);
            if ($semanas % $intervalo !== 0 || !in_array((int)$d->format('w'), $dias, true)) continue;
            [$h, $mi, $s] = array_map('intval', explode(':', $hora));
            if (!$aceita($d->setTime($h, $mi, $s))) break;
        }
    } elseif ($freq === 'MONTHLY') {
        $dia = isset($r['BYMONTHDAY']) ? (int)$r['BYMONTHDAY'] : (int)$inicio->format('j');
        for ($k = 0; $seguranca++ < 600; $k += $intervalo) {
            $m = (new DateTimeImmutable($inicio->format('Y-m-01'), $inicio->getTimezone()))->modify('+' . $k . ' months');
            if ($m > $fimJanela) break;
            $ultimo = (int)$m->format('t');
            $diaReal = $dia < 0 ? $ultimo + 1 + $dia : min($dia, $ultimo);
            [$h, $mi, $s] = array_map('intval', explode(':', $hora));
            $d = $m->setDate((int)$m->format('Y'), (int)$m->format('n'), max(1, $diaReal))->setTime($h, $mi, $s);
            if ($d < $inicio) continue;
            if (!$aceita($d)) break;
        }
    } elseif ($freq === 'YEARLY') {
        for ($k = 0; $seguranca++ < 100; $k += $intervalo) {
            $d = $inicio->modify('+' . $k . ' years');
            if ($d > $fimJanela) break;
            if (!$aceita($d)) break;
        }
    }
    return $out;
}
