<?php
/**
 * ENDPOINT — /backend/api/logout.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido.', [], 405);
}

logoutSession();
jsonResponse(true, 'Logout realizado com sucesso.');
