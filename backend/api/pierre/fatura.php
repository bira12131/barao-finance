<?php
/**
 * ENDPOINT — /backend/api/pierre/fatura.php
 *
 * GET ?conta_id=<pierre_id do cartão>&periodo=atual|proxima|anterior
 * Retorna os itens da fatura do cartão (compras e créditos) e o resumo por categoria.
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

$contaId = trim((string)($_GET['conta_id'] ?? ''));
$periodo = in_array($_GET['periodo'] ?? '', ['atual', 'proxima', 'anterior'], true) ? $_GET['periodo'] : 'atual';
if ($contaId === '') {
    jsonResponse(false, 'conta_id é obrigatório.', [], 422);
}

$userId = requireAuthenticatedUserId();

$guard = pierreGuardAccess($userId);
if (!$guard['allowed']) {
    jsonResponse(false, 'Dados Pierre indisponíveis para este usuário.', [
        'status' => 'acesso_negado_owner',
    ], 403);
}

try {
    $repo   = new PierreRepository($userId);
    $fatura = $repo->getItensFatura($contaId, $periodo);

    if ($fatura === null) {
        jsonResponse(false, 'Cartão não encontrado.', [], 404);
    }

    jsonResponse(true, 'Fatura retornada com sucesso.', ['fatura' => $fatura]);
} catch (\Exception $e) {
    jsonResponse(false, 'Erro ao buscar a fatura.', ['erro' => $e->getMessage()], 500);
}
