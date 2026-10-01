<?php
/**
 * ENDPOINT — /backend/api/pierre/status.php
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/pierre_guard.php';
require_once __DIR__ . '/../../repositories/PierreRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método não permitido.', [], 405);
}

try {
    $userId = requireAuthenticatedUserId();

    $guard = pierreGuardAccess($userId);
    if (!$guard['allowed']) {
        jsonResponse(true, 'Integração Pierre vinculada a outro usuário.', [
            'ultima_sync'      => null,
            'status'           => 'acesso_negado_owner',
            'api_configurada'  => true,
        ]);
    }

    $repo   = new PierreRepository($userId);
    $ultima = $repo->getUltimaSincronizacao();

    if (!$ultima) {
        jsonResponse(true, 'Ainda não sincronizado.', [
            'ultima_sync'       => null,
            'total_contas'      => 0,
            'total_transacoes'  => 0,
            'total_assinaturas' => 0,
            'status'            => 'nunca',
            'api_configurada'   => true,
        ]);
    }

    jsonResponse(true, 'Status da sincronização.', [
        'ultima_sync'        => $ultima['sincronizado_em'],
        'total_contas'       => (int) $ultima['total_contas'],
        'total_transacoes'   => (int) $ultima['total_transacoes'],
        'total_assinaturas'  => (int) $ultima['total_assinaturas'],
        'status'             => $ultima['status'],
        'detalhes'           => $ultima['detalhes'],
        'api_configurada'    => true,
    ]);

} catch (\Exception $e) {
    // Tabelas ainda não criadas — banco não inicializado
    jsonResponse(true, 'Banco não inicializado.', [
        'ultima_sync'      => null,
        'status'           => 'banco_nao_inicializado',
        'api_configurada'  => true,
    ]);
}
