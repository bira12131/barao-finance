<?php
/**
 * ENDPOINT — /backend/api/cofre.php  (precisa de login)
 *
 * O cofre é cifrado no navegador: aqui só trafega e fica guardado texto cifrado.
 *
 * GET  -> { configurado, config, itens: [{id, iv, ct, atualizado_em}] }
 * POST -> { acao: criar | trocar_mestra | trocar_recuperacao | salvar_item | excluir_item | importar | apagar_tudo, ... }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../repositories/CofreRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = requireAuthenticatedUserId();

try {
    $repo = new CofreRepository($userId);

    if ($metodo === 'GET') {
        $config = $repo->config();
        jsonResponse(true, 'Cofre retornado com sucesso.', [
            'configurado' => $config !== null,
            'config'      => $config,
            'itens'       => $config !== null ? $repo->itens() : [],
        ]);
    }

    if ($metodo !== 'POST') {
        jsonResponse(false, 'Método não permitido.', [], 405);
    }

    $p    = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $acao = (string)($p['acao'] ?? '');

    switch ($acao) {
        case 'criar':
            if (!$repo->criar((array)($p['config'] ?? []))) {
                jsonResponse(false, 'Não foi possível criar o cofre (já existe ou os dados são inválidos).', [], 422);
            }
            jsonResponse(true, 'Cofre criado.', [], 201);

        case 'trocar_mestra':
            if (!$repo->trocarMestra((array)($p['config'] ?? []))) jsonResponse(false, 'Não foi possível trocar a senha mestra.', [], 422);
            jsonResponse(true, 'Senha mestra trocada.');

        case 'trocar_recuperacao':
            if (!$repo->trocarRecuperacao((array)($p['config'] ?? []))) jsonResponse(false, 'Não foi possível trocar a chave de recuperação.', [], 422);
            jsonResponse(true, 'Chave de recuperação trocada.');

        case 'salvar_item':
            if (!$repo->salvarItem((string)($p['id'] ?? ''), $p)) jsonResponse(false, 'Não foi possível salvar o item.', [], 422);
            jsonResponse(true, 'Item salvo.');

        case 'excluir_item':
            if (!$repo->excluirItem((string)($p['id'] ?? ''))) jsonResponse(false, 'Item não encontrado.', [], 404);
            jsonResponse(true, 'Item excluído.');

        case 'importar':
            $n = $repo->importar(array_values((array)($p['itens'] ?? [])));
            jsonResponse(true, $n . ' item(ns) importado(s).', ['importados' => $n]);

        case 'apagar_tudo':
            // Última saída quando a senha mestra E a chave de recuperação foram perdidas.
            if (($p['confirmacao'] ?? '') !== 'APAGAR COFRE') jsonResponse(false, 'Confirmação incorreta.', [], 422);
            $repo->apagarTudo();
            jsonResponse(true, 'Cofre apagado.');
    }

    jsonResponse(false, 'Ação inválida.', [], 422);
} catch (InvalidArgumentException $e) {
    jsonResponse(false, $e->getMessage(), [], 422);
} catch (\Throwable $e) {
    jsonResponse(false, 'Erro ao processar o cofre.', [], 500);   // sem detalhes: nada sensível vaza em erro
}
