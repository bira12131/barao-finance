<?php
/**
 * ENDPOINT — /backend/api/agenda_icloud.php  (precisa de login)
 *
 * GET  -> estado da sincronização com o iCloud (sem segredos)
 * POST -> { acao: testar | selecionar | ativar | desativar | sincronizar, ... }
 *
 * As credenciais ficam em backend/config/icloud.php (fora do repositório); nunca passam pelo navegador.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../services/IcloudSyncService.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = requireAuthenticatedUserId();

try {
    $svc = new IcloudSyncService($userId);

    if ($metodo === 'GET') {
        jsonResponse(true, 'Estado retornado com sucesso.', $svc->estado());
    }

    if ($metodo !== 'POST') {
        jsonResponse(false, 'Método não permitido.', [], 405);
    }

    $p = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $acao = (string)($p['acao'] ?? '');

    if ($acao === 'testar') {
        jsonResponse(true, 'Conexão com o iCloud funcionando.', ['calendarios' => $svc->testar()] + $svc->estado());
    }

    if ($acao === 'selecionar') {
        $svc->selecionar(array_values(array_filter((array)($p['calendarios'] ?? []), 'is_string')));
        jsonResponse(true, 'Calendários atualizados.', $svc->estado());
    }

    if ($acao === 'ativar') {
        $svc->habilitar(true);
        $res = $svc->sincronizar();
        jsonResponse(true, 'Sincronização ativada.', ['resultado' => $res] + $svc->estado());
    }

    if ($acao === 'desativar') {
        $svc->habilitar(false);
        jsonResponse(true, 'Sincronização desativada.', $svc->estado());
    }

    if ($acao === 'sincronizar') {
        $res = $svc->sincronizar();
        jsonResponse(true, 'Sincronização concluída.', ['resultado' => $res] + $svc->estado());
    }

    jsonResponse(false, 'Ação inválida.', [], 422);
} catch (IcloudAuthException $e) {
    jsonResponse(false, $e->getMessage(), ['codigo' => 'auth'], 422);   // nunca 401: o app trataria como sessão expirada
} catch (\Throwable $e) {
    jsonResponse(false, $e->getMessage(), [], 500);
}
