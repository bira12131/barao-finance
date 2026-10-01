<?php
/**
 * Utilitário de resposta JSON padronizada.
 */

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
