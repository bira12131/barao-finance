<?php
/**
 * SERVICE — PierreService
 *
 * Cliente HTTP para a API Pierre Finance.
 * Toda comunicação com o Pierre Finance passa por aqui.
 * Nunca expõe a API key ao frontend.
 */

require_once __DIR__ . '/../config/pierre.php';
require_once __DIR__ . '/../repositories/PierreRepository.php';

class PierreService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = PIERRE_API_KEY;
        $this->baseUrl = PIERRE_BASE_URL;
    }

    /* ────────────────────────────────────────────
       Contas financeiras
       GET /tools/api/get-accounts
    ──────────────────────────────────────────── */
    public function getAccounts(): array
    {
        return $this->get('get-accounts');
    }

    /* ────────────────────────────────────────────
       Faturas fechadas dos cartões (vencimento, fechamento, total)
       GET /tools/api/get-bills
    ──────────────────────────────────────────── */
    public function getBills(): array
    {
        return $this->get('get-bills');
    }

    /* ────────────────────────────────────────────
       Saldo consolidado (somente contas bancárias)
       GET /tools/api/get-balance
    ──────────────────────────────────────────── */
    public function getBalance(): array
    {
        return $this->get('get-balance');
    }

    /* ────────────────────────────────────────────
       Transações
       GET /tools/api/get-transactions
    ──────────────────────────────────────────── */
    public function getTransactions(array $params = []): array
    {
        return $this->get('get-transactions', $params);
    }

    /* ────────────────────────────────────────────
       Sincronização manual de todas as contas
       POST /tools/api/manual-update
    ──────────────────────────────────────────── */
    public function sincronizar(): array
    {
        return $this->post('manual-update');
    }

    /* ────────────────────────────────────────────
       Sincronização completa: busca todos os dados
       da API Pierre e salva no banco PostgreSQL.
       Chamado após manual-update ou sob demanda.
    ──────────────────────────────────────────── */
    public function syncAll(PierreRepository $repo): array
    {
        $erros           = [];
        $totalContas     = 0;
        $totalTransacoes = 0;
        $totalAssinaturas = 0;

        // 1. Contas, faturas e transações são buscadas AO MESMO TEMPO (eram sequenciais: até 90 s no pior caso).
        $idsAntes = $this->idsContasSalvas($repo);
        $contasNovas = false;

        // Janela das transações: primeira execução 90 dias; depois, desde a última sync (com folga de 3 dias).
        $ultimaSync = $repo->getUltimaSincronizacao();
        $inicio = !empty($ultimaSync['sincronizado_em'])
            ? date('Y-m-d', strtotime($ultimaSync['sincronizado_em'] . ' -3 days'))
            : date('Y-m-d', strtotime('-90 days'));
        // +1 dia no fim evita cortar transações do dia atual por fuso horário.
        $fim = date('Y-m-d', strtotime('+1 day'));

        $r = $this->getMany([
            'contas' => ['get-accounts'],
            'faturas' => ['get-bills'],
            'transacoes' => ['get-transactions', ['startDate' => $inicio, 'endDate' => $fim]],
        ]);

        $resContas = $r['contas'];
        if ($resContas['success'] ?? false) {
            $contas = $resContas['data'] ?? [];
            // Conta nova (ex.: banco recém-conectado) precisa de histórico completo,
            // senão a busca incremental nunca traria as transações antigas dela.
            foreach ($contas as $c) {
                $id = $c['id'] ?? $c['accountId'] ?? $c['account_id'] ?? null;
                if ($id !== null && !in_array((string)$id, $idsAntes, true)) {
                    $contasNovas = true;
                    break;
                }
            }
            $totalContas = $repo->upsertContas($contas);
        } else {
            $erros[] = 'Contas: ' . ($resContas['message'] ?? 'erro desconhecido');
        }

        // Faturas fechadas (dão o fechamento real de cada cartão). Falha aqui não derruba o sync.
        try {
            if ($r['faturas']['success'] ?? false) {
                $repo->upsertFaturas($r['faturas']['data'] ?? []);
            }
        } catch (\Throwable $e) {
            $erros[] = 'Faturas: ' . $e->getMessage();
        }

        // Banco recém-conectado: busca o histórico maior só para ele (caso raro).
        $resTx = $r['transacoes'];
        if ($contasNovas && !empty($idsAntes) && $inicio > date('Y-m-d', strtotime('-180 days'))) {
            $resTx = $this->getTransactions(['startDate' => date('Y-m-d', strtotime('-180 days')), 'endDate' => $fim]);
        }
        if ($resTx['success'] ?? false) {
            $totalTransacoes = $repo->upsertTransacoes($resTx['data'] ?? []);
        } else {
            $erros[] = 'Transações: ' . ($resTx['message'] ?? 'erro desconhecido');
        }

        // 3. Detecta assinaturas só quando entraram transações NOVAS (ou contas novas);
        // reprocessar tudo a cada sync era a parte mais pesada.
        if ($repo->ultimoInseridos > 0 || $contasNovas) {
            try {
                $totalAssinaturas = $repo->detectarAssinaturas();
            } catch (\Exception $e) {
                $erros[] = 'Assinaturas: ' . $e->getMessage();
            }
        } else {
            $totalAssinaturas = count($repo->getAssinaturas());
        }

        $status = empty($erros) ? 'sucesso' : (($totalContas > 0 || $totalTransacoes > 0) ? 'parcial' : 'erro');

        // 4. Registra no log
        $repo->registrarSincronizacao([
            'tipo'              => 'completo',
            'status'            => $status,
            'total_contas'      => $totalContas,
            'total_transacoes'  => $totalTransacoes,
            'total_assinaturas' => $totalAssinaturas,
            'detalhes'          => empty($erros) ? null : implode('; ', $erros),
        ]);

        return [
            'success'            => $status !== 'erro',
            'status'             => $status,
            'total_contas'       => $totalContas,
            'total_transacoes'   => $totalTransacoes,
            'total_assinaturas'  => $totalAssinaturas,
            'erros'              => $erros,
        ];
    }

    /**
     * Sincronização RÁPIDA: só contas (saldos), cartões (limite/fatura) e faturas, em paralelo.
     * Não baixa transações nem recalcula assinaturas, por isso leva uma fração do syncAll.
     */
    public function syncContas(PierreRepository $repo): array
    {
        $erros = [];
        $r = $this->getMany(['contas' => ['get-accounts'], 'faturas' => ['get-bills']]);

        $totalContas = 0;
        if ($r['contas']['success'] ?? false) {
            $totalContas = $repo->upsertContas($r['contas']['data'] ?? []);
        } else {
            $erros[] = 'Contas: ' . ($r['contas']['message'] ?? 'erro desconhecido');
        }
        try {
            if ($r['faturas']['success'] ?? false) $repo->upsertFaturas($r['faturas']['data'] ?? []);
        } catch (\Throwable $e) {
            $erros[] = 'Faturas: ' . $e->getMessage();
        }

        $status = empty($erros) ? 'sucesso' : ($totalContas > 0 ? 'parcial' : 'erro');
        return [
            'success' => $status !== 'erro', 'status' => $status,
            'total_contas' => $totalContas, 'total_transacoes' => 0, 'total_assinaturas' => 0, 'erros' => $erros,
        ];
    }

    /** IDs (pierre_id) das contas já salvas, para detectar conexões novas. */
    private function idsContasSalvas(PierreRepository $repo): array
    {
        try {
            return array_map(
                static fn($c) => (string)($c['id'] ?? $c['accountId'] ?? ''),
                $repo->getContas('')
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Alguns ambientes de hospedagem falham na validação SSL por CA bundle ausente.
     * Se detectarmos erro SSL do cURL, tentamos uma segunda chamada sem verificação.
     */
    private function shouldRetryWithoutSslVerify(int $curlErrno): bool
    {
        return in_array($curlErrno, [35, 51, 58, 60, 77, 83], true);
    }

    /* ════════════════════════════════════════════
       HTTP helper — GET com Bearer token via cURL
    ════════════════════════════════════════════ */
    private function get(string $endpoint, array $params = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = (int) curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false && $this->shouldRetryWithoutSslVerify($curlErrno)) {
            $ch = curl_init($url);
            $retryOptions = $options;
            $retryOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $retryOptions[CURLOPT_SSL_VERIFYHOST] = 0;
            curl_setopt_array($ch, $retryOptions);

            $response  = curl_exec($ch);
            $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = (int) curl_errno($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
        }

        return $this->interpretar($response, $httpCode, $curlErrno, $curlError);
    }

    /** Converte a resposta HTTP crua da Pierre no formato padrão do serviço. */
    private function interpretar($response, int $httpCode, int $curlErrno, string $curlError): array
    {
        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Erro de conexão com Pierre Finance (cURL #' . $curlErrno . ').',
                'detail'  => $curlError,
            ];
        }

        $data = json_decode($response, true);

        if ($data === null) {
            return ['success' => false, 'message' => 'Resposta inválida da API Pierre Finance.'];
        }

        if ($httpCode === 401) {
            return ['success' => false, 'message' => 'API key inválida ou assinatura inativa.', 'code' => 401];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => $data['message'] ?? 'Erro na API Pierre Finance.',
                'code'    => $httpCode,
            ];
        }

        return $data;
    }

    /**
     * GETs em paralelo (curl_multi): o tempo total vira o da chamada mais lenta, não a soma.
     * @param array<string,array{0:string,1?:array}> $pedidos chave => [endpoint, params]
     * @return array<string,array> chave => resposta padrão
     */
    private function getMany(array $pedidos): array
    {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($pedidos as $chave => $pedido) {
            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($pedido[0], '/');
            if (!empty($pedido[1])) $url .= '?' . http_build_query($pedido[1]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$chave] = $ch;
        }

        do {
            $status = curl_multi_exec($multi, $ativos);
            if ($ativos) curl_multi_select($multi, 1.0);
        } while ($ativos && $status === CURLM_OK);

        $out = [];
        foreach ($handles as $chave => $ch) {
            $resposta = curl_multi_getcontent($ch);
            $erro = (int)curl_errno($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $msg = curl_error($ch);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);

            if (($resposta === false || $resposta === null || $resposta === '') && $this->shouldRetryWithoutSslVerify($erro)) {
                // Falha de SSL do ambiente: refaz esta chamada pelo caminho sequencial, que já tem o plano B.
                $out[$chave] = $this->get($pedidos[$chave][0], $pedidos[$chave][1] ?? []);
                continue;
            }
            $out[$chave] = $this->interpretar($resposta === '' ? false : $resposta, $http, $erro, $msg);
        }
        curl_multi_close($multi);
        return $out;
    }

    /* ════════════════════════════════════════════
       HTTP helper — POST sem corpo
    ════════════════════════════════════════════ */
    private function post(string $endpoint): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: 0',
            ],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = (int) curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false && $this->shouldRetryWithoutSslVerify($curlErrno)) {
            $ch = curl_init($url);
            $retryOptions = $options;
            $retryOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $retryOptions[CURLOPT_SSL_VERIFYHOST] = 0;
            curl_setopt_array($ch, $retryOptions);

            $response  = curl_exec($ch);
            $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = (int) curl_errno($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
        }

        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Erro de conexão com Pierre Finance (cURL #' . $curlErrno . ').',
                'detail'  => $curlError,
            ];
        }

        $data = json_decode($response, true);

        if ($data === null) {
            return ['success' => false, 'message' => 'Resposta inválida da API Pierre Finance.'];
        }

        if ($httpCode === 401) {
            return ['success' => false, 'message' => 'API key inválida ou assinatura inativa.', 'code' => 401];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => $data['message'] ?? 'Erro na API Pierre Finance.',
                'code'    => $httpCode,
            ];
        }

        return $data;
    }
}
