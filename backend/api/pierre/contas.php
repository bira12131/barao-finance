<?php
/**
 * ENDPOINT — /backend/api/pierre/contas.php
 *
 * Retorna contas financeiras. Fonte primária: banco PostgreSQL.
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

$tipo   = strtoupper(trim($_GET['tipo'] ?? ''));
$userId = requireAuthenticatedUserId();

$guard = pierreGuardAccess($userId);
if (!$guard['allowed']) {
    jsonResponse(false, 'Dados Pierre indisponíveis para este usuário.', [
        'status' => 'acesso_negado_owner',
    ], 403);
}

function normalizarConta(array $c): array
{
    $c['id']                   = $c['id'] ?? ($c['accountId'] ?? ($c['account_id'] ?? null));
    $c['accountId']            = $c['accountId'] ?? ($c['account_id'] ?? $c['id']);
    $c['accountName']          = $c['accountName'] ?? ($c['name'] ?? null);
    $c['accountMarketingName'] = $c['accountMarketingName'] ?? ($c['marketingName'] ?? null);
    $c['providerCode']         = $c['providerCode'] ?? ($c['connectorName'] ?? null);
    $c['accountType']          = $c['accountType'] ?? ($c['type'] ?? null);
    $c['accountSubtype']       = $c['accountSubtype'] ?? ($c['subtype'] ?? null);
    $c['accountBalance']       = isset($c['accountBalance'])
        ? (float)$c['accountBalance']
        : (isset($c['balance']) ? (float)$c['balance'] : 0.0);

    return $c;
}

try {
    $repo = new PierreRepository($userId);

    $contas = array_map('normalizarConta', $repo->getContas($tipo));
    jsonResponse(true, 'Contas retornadas com sucesso.', ['contas' => $contas, 'total' => count($contas), 'fonte' => 'banco']);

} catch (\Exception $e) {
    jsonResponse(false, 'Erro ao buscar contas no armazenamento local.', [
        'erro' => $e->getMessage(),
    ], 500);
}
