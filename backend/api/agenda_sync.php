<?php
/**
 * ENDPOINT — /backend/api/agenda_sync.php  (precisa de login)
 *
 * GET  -> { url, webcal_url }: link secreto para o iPhone assinar a agenda
 * POST -> { acao: 'regenerar' }: gera um link novo e invalida o anterior
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../repositories/AgendaRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = requireAuthenticatedUserId();

try {
    $repo = new AgendaRepository($userId);

    $regenerar = false;
    if ($metodo === 'POST') {
        $p = json_decode((string)file_get_contents('php://input'), true) ?: [];
        if (($p['acao'] ?? '') !== 'regenerar') {
            jsonResponse(false, 'Ação inválida.', [], 422);
        }
        $regenerar = true;
    } elseif ($metodo !== 'GET') {
        jsonResponse(false, 'Método não permitido.', [], 405);
    }

    $token = $repo->tokenIcs($regenerar);

    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $pasta  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/backend/api/agenda_sync.php'), '/');
    $caminho = $host . $pasta . '/agenda_ics.php?t=' . $token;

    jsonResponse(true, $regenerar ? 'Novo link gerado.' : 'Link retornado com sucesso.', [
        'url'        => ($https ? 'https://' : 'http://') . $caminho,
        'webcal_url' => 'webcal://' . $caminho,
        'seguro'     => $https,
    ]);
} catch (\Throwable $e) {
    jsonResponse(false, 'Erro ao gerar o link do calendário.', ['detalhe' => $e->getMessage()], 500);
}
