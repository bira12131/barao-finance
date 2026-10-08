<?php
/**
 * Utilitário de resposta JSON padronizada.
 */

// Todo o app trabalha em horário de Brasília. Sem isso, um servidor em UTC vira o "hoje" às 21h.
date_default_timezone_set('America/Sao_Paulo');

/**
 * Envia resposta JSON e encerra a execução.
 *
 * @param bool   $success
 * @param string $message  Mensagem legível
 * @param array  $data     Payload opcional
 * @param int    $code     HTTP status code
 */
function jsonResponse(bool $success, string $message, array $data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
