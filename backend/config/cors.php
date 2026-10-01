<?php
/**
 * Cabeçalhos CORS
 * Permite chamadas do frontend hospedado no mesmo domínio
 * e do localhost durante o desenvolvimento.
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowed_origins = [
    'https://elinka.com.br',
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:8080',
    'http://127.0.0.1:8080',
];

if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
} else {
    // Mesmo domínio (sem cabeçalho Origin) — permite
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Responde preflight imediatamente
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
