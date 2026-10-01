<?php
/**
 * ENDPOINT — /backend/api/pierre/transacoes.php
 *
 * Retorna transações. Fonte primária: banco PostgreSQL.
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

$permitidos = ['startDate','endDate','accountType','tipo','categoria','minAmount','maxAmount','limit'];
$filtros    = [];
foreach ($permitidos as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') $filtros[$k] = $_GET[$k];
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

    $transacoes = $repo->getTransacoes($filtros);
    jsonResponse(true, 'Transações retornadas com sucesso.', [
        'transacoes' => $transacoes,
        'total'      => count($transacoes),
        'filtros'    => $filtros,
        'fonte'      => 'banco',
    ]);

} catch (\Exception $e) {
    jsonResponse(false, 'Erro ao buscar transações no armazenamento local.', [
        'erro' => $e->getMessage(),
    ], 500);
}
