<?php
/**
 * ENDPOINT — /backend/api/snippets.php  (precisa de login)
 *
 * GET  ?q=&linguagem=&tag=&favoritos=1  -> lista (com trecho) + tags em uso
 * GET  ?id=<uuid>                       -> um item completo
 * POST { acao: criar | editar | excluir | favoritar | importar, ... }   (tudo via POST: o .htaccess só libera GET/POST)
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../repositories/SnippetsRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = requireAuthenticatedUserId();

try {
    $repo = new SnippetsRepository($userId);

    if ($metodo === 'GET') {
        if (!empty($_GET['id'])) {
            $s = $repo->obter((string)$_GET['id']);
            if ($s === null) jsonResponse(false, 'Item não encontrado.', [], 404);
            jsonResponse(true, 'Item retornado com sucesso.', ['item' => $s]);
        }
        jsonResponse(true, 'Itens retornados com sucesso.', [
            'itens' => $repo->listar($_GET['q'] ?? null, $_GET['linguagem'] ?? null, ($_GET['favoritos'] ?? '') === '1', $_GET['tag'] ?? null),
            'tags'  => $repo->tags(),
        ]);
    }

    if ($metodo !== 'POST') {
        jsonResponse(false, 'Método não permitido.', [], 405);
    }

    $p    = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $acao = (string)($p['acao'] ?? 'criar');

    if ($acao === 'criar' || $acao === 'editar') {
        $d = $repo->normalizar($p);
        if ($d === null) jsonResponse(false, 'Informe um nome (até 200 caracteres) e um conteúdo de até 500 KB.', [], 422);
        if ($acao === 'criar') {
            $id = $repo->criar($d);
            if ($id === null) jsonResponse(false, 'Limite de itens atingido.', [], 422);
            jsonResponse(true, 'Item criado.', ['id' => $id], 201);
        }
        if (!$repo->atualizar((string)($p['id'] ?? ''), $d)) jsonResponse(false, 'Item não encontrado.', [], 404);
        jsonResponse(true, 'Item atualizado.');
    }

    if ($acao === 'excluir') {
        if (!$repo->excluir((string)($p['id'] ?? ''))) jsonResponse(false, 'Item não encontrado.', [], 404);
        jsonResponse(true, 'Item excluído.');
    }

    if ($acao === 'favoritar') {
        if (!$repo->favoritar((string)($p['id'] ?? ''), filter_var($p['favorito'] ?? false, FILTER_VALIDATE_BOOLEAN))) jsonResponse(false, 'Item não encontrado.', [], 404);
        jsonResponse(true, 'Item atualizado.');
    }

    if ($acao === 'importar') {
        $lista = array_slice(array_values((array)($p['itens'] ?? [])), 0, 100);
        $criados = 0;
        foreach ($lista as $it) {
            $d = is_array($it) ? $repo->normalizar($it) : null;
            if ($d !== null && $repo->criar($d) !== null) $criados++;
        }
        jsonResponse(true, $criados . ' arquivo(s) importado(s).', ['importados' => $criados]);
    }

    jsonResponse(false, 'Ação inválida.', [], 422);
} catch (\Throwable $e) {
    jsonResponse(false, 'Erro ao processar os códigos.', [], 500);
}
