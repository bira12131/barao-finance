<?php
/**
 * ENDPOINT PÚBLICO — /backend/api/whatsapp_webhook.php?s=<segredo>
 *
 * Webhook da Evolution API (evento MESSAGES_UPSERT). Sem cookie de login: o acesso é pelo segredo na URL
 * e, dentro, só o número autorizado em config/whatsapp.php é atendido.
 * Responde 200 na hora e processa depois, para a Evolution não repetir o envio por timeout.
 */

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../services/WhatsappService.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

$servico = new WhatsappService();
if (!$servico->segredoValido((string)($_GET['s'] ?? ''))) {
    http_response_code(404);   // não revela que o endpoint existe
    exit;
}

$evt = json_decode((string)file_get_contents('php://input'), true);

ignore_user_abort(true);
set_time_limit(120);
header('Content-Type: application/json');
header('Connection: close');
header('Content-Length: 2');
echo '{}';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_end_flush();
    flush();
}

if (is_array($evt)) {
    $servico->tratar($evt);
}
