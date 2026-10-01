<?php
/**
 * VIEW (entry point) — POST /backend/api/register.php
 *
 * Responsabilidade: ler a requisição HTTP e delegar ao Controller.
 * Não contém lógica de negócio nem acesso ao banco.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido.', [], 405);
}

$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    jsonResponse(false, 'Requisição inválida.', [], 400);
}

(new AuthController())->register($body);
