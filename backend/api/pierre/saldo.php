<?php
/**
 * ENDPOINT — /backend/api/pierre/saldo.php
 *
 * Retorna saldo consolidado. Fonte primária: banco PostgreSQL.
 * Fallback automático: API Pierre Finance (caso tabelas não existam ainda).
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

$userId = requireAuthenticatedUserId();

$guard = pierreGuardAccess($userId);
if (!$guard['allowed']) {
    jsonResponse(false, 'Dados Pierre indisponíveis para este usuário.', [
        'status' => 'acesso_negado_owner',
    ], 403);
}

try {
    $repo = new PierreRepository($userId);

    $totalBalance = $repo->getSaldoTotal();
    $accounts     = $repo->getSaldoPorConta();

    jsonResponse(true, 'Saldo retornado com sucesso.', [
        'total_balance' => $totalBalance,
        'accounts'      => $accounts,
        'fonte'         => 'banco',
    ]);

} catch (\Exception $e) {
    jsonResponse(false, 'Erro ao buscar saldo no armazenamento local.', [
        'erro' => $e->getMessage(),
    ], 500);
}
