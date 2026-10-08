<?php
/**
 * Cliente CalDAV mínimo para o iCloud (caldav.icloud.com).
 *
 * Autenticação: Apple ID + senha específica de app (HTTP Basic). Nunca a senha do Apple ID.
 * A conexão exige certificado SSL válido (não há fallback inseguro: a senha trafega nela).
 */

class IcloudAuthException extends RuntimeException {}

class IcloudCalDav
{
    private string $usuario;
    private string $senha;
    private string $base;
    private string $hostAtual;

    public function __construct(string $usuario, string $senha, string $base = 'https://caldav.icloud.com')
    {
        $this->usuario   = $usuario;
        $this->senha     = $senha;
        $this->base      = rtrim($base, '/');
        $this->hostAtual = $this->base;
    }

    /* ════════════════════════════════════════════
       HTTP
    ════════════════════════════════════════════ */

    /** Resolve href relativo contra o host do último pedido; mantém URLs absolutas. */
    public function absoluta(string $href, ?string $referencia = null): string
    {
        $href = trim($href);
        if (preg_match('#^https?://#i', $href)) return $href;

        $ref = $referencia && preg_match('#^(https?://[^/]+)#i', $referencia, $m) ? $m[1] : $this->hostAtual;
        return $ref . '/' . ltrim($href, '/');
    }

    /** @return array{status:int, headers:array<string,string>, body:string} */
    public function pedido(string $metodo, string $url, array $cabecalhos = [], ?string $corpo = null, int $redirecionamentos = 0): array
    {
        $url = $this->absoluta($url);
        if (preg_match('#^(https?://[^/]+)#i', $url, $m)) $this->hostAtual = $m[1];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => array_merge(
                ['Authorization: Basic ' . base64_encode($this->usuario . ':' . $this->senha), 'Accept: */*'],
                $cabecalhos
            ),
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => 'BaraoFinance/1.0 (CalDAV)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($corpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo);

        $resposta = curl_exec($ch);
        if ($resposta === false) {
            $n = curl_errno($ch);
            $erro = curl_error($ch);
            curl_close($ch);
            $dica = in_array($n, [35, 51, 58, 60, 77, 83], true) ? ' O servidor não conseguiu validar o certificado SSL (falta o pacote de certificados do PHP).' : '';
            throw new RuntimeException('Falha de conexão com o iCloud (cURL #' . $n . '): ' . $erro . '.' . $dica);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tam    = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $cab = [];
        foreach (explode("\r\n", substr($resposta, 0, $tam)) as $linha) {
            if (strpos($linha, ':') !== false) {
                [$k, $v] = explode(':', $linha, 2);
                $cab[strtolower(trim($k))] = trim($v);
            }
        }
        $res = ['status' => $status, 'headers' => $cab, 'body' => (string)substr($resposta, $tam)];

        if (in_array($status, [301, 302, 307, 308], true) && !empty($cab['location']) && $redirecionamentos < 3) {
            return $this->pedido($metodo, $this->absoluta($cab['location'], $url), $cabecalhos, $corpo, $redirecionamentos + 1);
        }
        if ($status === 401 || $status === 403) {
            throw new IcloudAuthException('O iCloud recusou o acesso: confira o Apple ID e a senha específica de app em backend/config/icloud.php.');
        }
        return $res;
    }

    /* ════════════════════════════════════════════
       XML
    ════════════════════════════════════════════ */

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Resposta inesperada do iCloud (XML inválido).');
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('d', 'DAV:');
        $xp->registerNamespace('c', 'urn:ietf:params:xml:ns:caldav');
        $xp->registerNamespace('cs', 'http://calendarserver.org/ns/');
        $xp->registerNamespace('ic', 'http://apple.com/ns/ical/');
        return $xp;
    }

    private function texto(DOMXPath $xp, string $consulta, ?DOMNode $ctx = null): ?string
    {
        $n = $ctx ? $xp->query($consulta, $ctx) : $xp->query($consulta);
        return ($n && $n->length) ? trim($n->item(0)->textContent) : null;
    }

    /* ════════════════════════════════════════════
       Descoberta
    ════════════════════════════════════════════ */

    /** @return array{principal:string, home:string} */
    public function descobrir(): array
    {
        $cab = ['Depth: 0', 'Content-Type: application/xml; charset=utf-8'];

        $r = $this->pedido('PROPFIND', $this->base . '/', $cab,
            '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:current-user-principal/></d:prop></d:propfind>');
        if ($r['status'] !== 207) throw new RuntimeException('O iCloud não respondeu à descoberta da conta (HTTP ' . $r['status'] . ').');
        $href = $this->texto($this->xpath($r['body']), '//d:current-user-principal/d:href');
        if (!$href) throw new RuntimeException('O iCloud não informou a conta (principal).');
        $principal = $this->absoluta($href);

        $r = $this->pedido('PROPFIND', $principal, $cab,
            '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:prop><c:calendar-home-set/></d:prop></d:propfind>');
        if ($r['status'] !== 207) throw new RuntimeException('O iCloud não informou onde ficam os calendários (HTTP ' . $r['status'] . ').');
        $home = $this->texto($this->xpath($r['body']), '//c:calendar-home-set/d:href');
        if (!$home) throw new RuntimeException('O iCloud não informou a pasta de calendários.');

        return ['principal' => $principal, 'home' => rtrim($this->absoluta($home, $principal), '/') . '/'];
    }

    /**
     * Calendários de eventos da conta (exclui lembretes, calendários assinados, caixa de entrada etc.).
     * @return array<int, array{href:string, nome:string, cor:?string}>
     */
    public function calendarios(string $home): array
    {
        $r = $this->pedido('PROPFIND', $home, ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'],
            '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:ic="http://apple.com/ns/ical/">'
            . '<d:prop><d:displayname/><d:resourcetype/><c:supported-calendar-component-set/><ic:calendar-color/></d:prop></d:propfind>');
        if ($r['status'] !== 207) throw new RuntimeException('Não foi possível listar os calendários (HTTP ' . $r['status'] . ').');

        $xp = $this->xpath($r['body']);
        $out = [];
        foreach ($xp->query('//d:response') as $resp) {
            $ehCalendario = $xp->query('.//d:resourcetype/c:calendar', $resp)->length > 0;
            $assinado     = $xp->query('.//d:resourcetype/cs:subscribed', $resp)->length > 0;
            if (!$ehCalendario || $assinado) continue;

            $comps = [];
            foreach ($xp->query('.//c:supported-calendar-component-set/c:comp', $resp) as $c) $comps[] = strtoupper($c->getAttribute('name'));
            if ($comps && !in_array('VEVENT', $comps, true)) continue;   // calendário só de lembretes (VTODO)

            $href = $this->texto($xp, 'd:href', $resp);
            if (!$href) continue;
            $out[] = [
                'href' => $this->absoluta($href, $home),
                'nome' => $this->texto($xp, './/d:displayname', $resp) ?: 'Calendário',
                'cor'  => $this->texto($xp, './/ic:calendar-color', $resp),
            ];
        }
        return $out;
    }

    /** Cria o calendário (MKCALENDAR) e devolve o href. Se já existir, devolve o mesmo href. */
    public function criarCalendario(string $home, string $slug, string $nome, string $corHex = '#7a6cf0'): string
    {
        $url = rtrim($home, '/') . '/' . $slug . '/';
        $r = $this->pedido('MKCALENDAR', $url, ['Content-Type: application/xml; charset=utf-8'],
            '<?xml version="1.0" encoding="utf-8"?><c:mkcalendar xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:ic="http://apple.com/ns/ical/">'
            . '<d:set><d:prop><d:displayname>' . htmlspecialchars($nome, ENT_XML1) . '</d:displayname>'
            . '<c:supported-calendar-component-set><c:comp name="VEVENT"/></c:supported-calendar-component-set>'
            . '<ic:calendar-color>' . htmlspecialchars($corHex, ENT_XML1) . 'FF</ic:calendar-color></d:prop></d:set></c:mkcalendar>');
        if (!in_array($r['status'], [201, 405], true)) {   // 405 = já existe
            throw new RuntimeException('Não foi possível criar o calendário no iCloud (HTTP ' . $r['status'] . ').');
        }
        return $url;
    }

    /* ════════════════════════════════════════════
       Eventos
    ════════════════════════════════════════════ */

    /**
     * Eventos de um calendário. Com $inicio/$fim (UTC, "Ymd\THis\Z") filtra o período e, se $expandir,
     * pede ao servidor que expanda as repetições em ocorrências.
     * @return array<int, array{href:string, etag:?string, ics:string}>
     */
    public function eventos(string $calendario, ?string $inicio = null, ?string $fim = null, bool $expandir = false): array
    {
        $intervalo = ($inicio && $fim) ? '<c:time-range start="' . $inicio . '" end="' . $fim . '"/>' : '';
        $expand = ($inicio && $fim && $expandir) ? '<c:expand start="' . $inicio . '" end="' . $fim . '"/>' : '';
        $corpo = '<?xml version="1.0" encoding="utf-8"?><c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'
            . '<d:prop><d:getetag/><c:calendar-data>' . $expand . '</c:calendar-data></d:prop>'
            . '<c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT">' . $intervalo . '</c:comp-filter></c:comp-filter></c:filter>'
            . '</c:calendar-query>';

        $r = $this->pedido('REPORT', $calendario, ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'], $corpo);
        if ($r['status'] !== 207) throw new RuntimeException('Não foi possível ler os eventos (HTTP ' . $r['status'] . ').');

        $xp = $this->xpath($r['body']);
        $out = [];
        foreach ($xp->query('//d:response') as $resp) {
            $ics = $this->texto($xp, './/c:calendar-data', $resp);
            $href = $this->texto($xp, 'd:href', $resp);
            if ($ics === null || $ics === '' || !$href) continue;
            $out[] = ['href' => $this->absoluta($href, $calendario), 'etag' => $this->texto($xp, './/d:getetag', $resp), 'ics' => $ics];
        }
        return $out;
    }

    /** ETag atual de um recurso (null se não existir). */
    public function etag(string $href): ?string
    {
        $r = $this->pedido('PROPFIND', $href, ['Depth: 0', 'Content-Type: application/xml; charset=utf-8'],
            '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:getetag/></d:prop></d:propfind>');
        return $r['status'] === 207 ? $this->texto($this->xpath($r['body']), '//d:getetag') : null;
    }

    /**
     * Cria (sem $etag: falha se já existir) ou atualiza ($etag: falha se mudou no servidor).
     * @return array{status:int, etag:?string}   status 201/204 = ok; 412 = conflito; 404 = sumiu
     */
    public function gravar(string $href, string $ics, ?string $etag = null): array
    {
        $cab = ['Content-Type: text/calendar; charset=utf-8', $etag !== null && $etag !== '' ? 'If-Match: ' . $etag : 'If-None-Match: *'];
        $r = $this->pedido('PUT', $href, $cab, $ics);
        $novo = null;
        if (in_array($r['status'], [200, 201, 204], true)) {
            $novo = $r['headers']['etag'] ?? $this->etag($href);
        }
        return ['status' => $r['status'], 'etag' => $novo];
    }

    /** Apaga o evento. 200/204/404/410 contam como removido. */
    public function apagar(string $href, ?string $etag = null): int
    {
        $cab = $etag ? ['If-Match: ' . $etag] : [];
        return $this->pedido('DELETE', $href, $cab)['status'];
    }
}
