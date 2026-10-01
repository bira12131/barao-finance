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

        // 1. Busca e salva contas (bancárias + cartões)
        $resContas = $this->getAccounts();
        if ($resContas['success'] ?? false) {
            $contas      = $resContas['data'] ?? [];
            $totalContas = $repo->upsertContas($contas);
        } else {
            $erros[] = 'Contas: ' . ($resContas['message'] ?? 'erro desconhecido');
        }

        // 2. Busca incremental de transações para reduzir tempo de sync.
        // Primeira execução: 90 dias. Próximas: reaproveita última sync com janela de segurança de 3 dias.
        $ultimaSync = $repo->getUltimaSincronizacao();
        $inicio = date('Y-m-d', strtotime('-90 days'));
        if (!empty($ultimaSync['sincronizado_em'])) {
            $inicio = date('Y-m-d', strtotime($ultimaSync['sincronizado_em'] . ' -3 days'));
        }

        // Usa +1 dia no fim para evitar corte de transações do dia atual por fuso horário.
        $fim     = date('Y-m-d', strtotime('+1 day'));
        $resTx   = $this->getTransactions(['startDate' => $inicio, 'endDate' => $fim]);
        if ($resTx['success'] ?? false) {
            $transacoes      = $resTx['data'] ?? [];
            $totalTransacoes = $repo->upsertTransacoes($transacoes);
        } else {
            $erros[] = 'Transações: ' . ($resTx['message'] ?? 'erro desconhecido');
        }

        // 3. Detecta assinaturas só quando houve entrada nova de transações,
        // evitando processamento pesado sem necessidade.
        if ($totalTransacoes > 0) {
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
