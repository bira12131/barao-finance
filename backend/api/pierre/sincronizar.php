<?php
/**
 * ENDPOINT — /backend/api/pierre/sincronizar.php
 *
 * Fluxo:
 *  1. Chama POST /manual-update na Pierre Finance (força sync das contas conectadas)
 *  2. Faz syncAll(): busca contas + transações da API e salva no PostgreSQL
 *  3. Detecta assinaturas recorrentes
 *  4. Retorna resumo
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/pierre_guard.php';
require_once __DIR__ . '/../../services/PierreService.php';
require_once __DIR__ . '/../../repositories/PierreRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido.', [], 405);
}

try {
    $pierre = new PierreService();
    $userId = requireAuthenticatedUserId();

    $guard = pierreGuardAccess($userId);
    if (!$guard['allowed']) {
        jsonResponse(false, 'Integração Pierre vinculada a outro usuário deste ambiente.', [
            'status' => 'acesso_negado_owner',
        ], 403);
    }

    $repo   = new PierreRepository($userId);

    // 1. Dispara o manual-update na Pierre Finance (ignora falha — nem sempre necessário)
    $pierre->sincronizar();

    // 2. Busca todos os dados e salva no banco
    $resultado = $pierre->syncAll($repo);

    if (!$resultado['success'] && $resultado['status'] === 'erro') {
        jsonResponse(false, 'Erro ao sincronizar dados.', [
            'erros' => $resultado['erros'],
        ], 502);
    }

    jsonResponse(true, 'Sincronização concluída.', [
        'total_contas'      => $resultado['total_contas'],
        'total_transacoes'  => $resultado['total_transacoes'],
        'total_assinaturas' => $resultado['total_assinaturas'],
        'status'            => $resultado['status'],
        'erros'             => $resultado['erros'],
    ]);
} catch (\Throwable $e) {
    $rawError = $e->getMessage();
    $msg = 'Falha interna ao sincronizar.';
    $code = 500;

    if (stripos($rawError, 'relation') !== false && stripos($rawError, 'pierre_') !== false) {
        $msg = 'Banco não inicializado para o Pierre. Execute o SQL de criação das tabelas.';
        $code = 503;
    } elseif (stripos($rawError, 'curl_init') !== false || stripos($rawError, 'undefined function curl_') !== false) {
        $msg = 'Servidor sem extensão cURL ativa. Habilite cURL no PHP para sincronizar com o Pierre.';
        $code = 503;
    } elseif (stripos($rawError, 'Sessão inválida') !== false || stripos($rawError, 'expirada') !== false) {
        $msg = 'Sessão expirada. Faça login novamente para sincronizar.';
        $code = 401;
    }

    jsonResponse(false, $msg, [
        'erro' => $rawError,
    ], $code);
}

