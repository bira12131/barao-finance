<?php
/**
 * ENDPOINT — /backend/api/pierre/categorias.php
 *
 * GET  -> lista categorias cadastradas (ou combinadas com query source=all)
 * POST -> cria categoria manual
 * PATCH/POST -> atualiza categoria de transação
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../repositories/PierreRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = requireAuthenticatedUserId();
$repo   = new PierreRepository($userId);

if ($metodo === 'GET') {
    $source = strtolower(trim((string)($_GET['source'] ?? 'custom')));
    $categorias = $source === 'all'
        ? $repo->getCategoriasDisponiveis()
        : $repo->getCategoriasCadastradas();

    jsonResponse(true, 'Categorias retornadas com sucesso.', [
        'categorias' => $categorias,
        'total'      => count($categorias),
        'source'     => $source,
    ]);
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$acao    = strtolower(trim($payload['acao'] ?? ''));

if ($metodo === 'POST' && ($acao === 'criar' || isset($payload['categoria']) && !isset($payload['transactionId']))) {
    $categoria = trim((string)($payload['categoria'] ?? ''));
    if ($categoria === '') {
        jsonResponse(false, 'Categoria é obrigatória.', [], 422);
    }

    $repo->criarCategoria($categoria);
    $categorias = $repo->getCategoriasCadastradas();

    jsonResponse(true, 'Categoria criada com sucesso.', [
        'categoria'  => $categoria,
        'categorias' => $categorias,
    ]);
}

if ($metodo === 'POST' && $acao === 'renomear') {
    $categoriaAntiga = trim((string)($payload['categoriaAntiga'] ?? ''));
    $categoriaNova   = trim((string)($payload['categoriaNova'] ?? ''));

    if ($categoriaAntiga === '' || $categoriaNova === '') {
        jsonResponse(false, 'categoriaAntiga e categoriaNova são obrigatórias.', [], 422);
    }

    $repo->renomearCategoria($categoriaAntiga, $categoriaNova);
    $categorias = $repo->getCategoriasCadastradas();

    jsonResponse(true, 'Categoria renomeada com sucesso.', [
        'categoria_antiga' => $categoriaAntiga,
        'categoria_nova'   => $categoriaNova,
        'categorias'       => $categorias,
    ]);
}

if ($metodo === 'PATCH' || $metodo === 'POST') {
    $transactionId = trim((string)($payload['transactionId'] ?? $payload['id'] ?? ''));
    $categoria     = trim((string)($payload['categoria'] ?? ''));

    if ($transactionId === '' || $categoria === '') {
        jsonResponse(false, 'transactionId e categoria são obrigatórios.', [], 422);
    }

    $ok = $repo->atualizarCategoriaTransacao($transactionId, $categoria);

    if (!$ok) {
        jsonResponse(false, 'Transação não encontrada para atualização.', [], 404);
    }

    jsonResponse(true, 'Categoria atualizada com sucesso.', [
        'transactionId' => $transactionId,
        'categoria'     => $categoria,
    ]);
}

jsonResponse(false, 'Método não permitido.', [], 405);
