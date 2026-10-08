<?php
/**
 * ENDPOINT — /backend/api/agenda.php
 *
 * GET    ?mes=YYYY-MM            -> itens do mês (eventos + vencimentos financeiros)
 * GET    ?inicio=...&fim=...     -> itens de um período (máx. 366 dias)
 * POST                           -> cria evento
 * PATCH  (ou POST + _method)    -> edita evento (ou { id, concluido } para marcar tarefa)
 * DELETE (ou POST + _method)    -> exclui evento
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../repositories/AgendaRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = requireAuthenticatedUserId();

// O servidor (.htaccess) só libera GET/POST/OPTIONS: PATCH e DELETE chegam via POST + _method.
$rawBody = $metodo === 'GET' ? '' : (string)file_get_contents('php://input');
$body    = json_decode($rawBody, true) ?: [];
if ($metodo === 'POST') {
    $override = strtoupper((string)($body['_method'] ?? ($_GET['_method'] ?? '')));
    if (in_array($override, ['PATCH', 'DELETE'], true)) {
        $metodo = $override;
    }
}

try {
    $repo = new AgendaRepository($userId);
} catch (\Throwable $e) {
    jsonResponse(false, 'Erro ao acessar a agenda.', ['detalhe' => $e->getMessage()], 500);
}

$payload = $body;

try {
    if ($metodo === 'GET') {
        $mes = trim((string)($_GET['mes'] ?? ''));
        if ($mes !== '') {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
                jsonResponse(false, 'mes deve estar no formato YYYY-MM.', [], 422);
            }
            $inicio = $mes . '-01';
            $fim    = date('Y-m-t', strtotime($inicio));
        } else {
            $inicio = trim((string)($_GET['inicio'] ?? date('Y-m-d')));
            $fim    = trim((string)($_GET['fim'] ?? date('Y-m-d', strtotime('+30 days'))));
            $okFmt  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim);
            if (!$okFmt || $fim < $inicio || (strtotime($fim) - strtotime($inicio)) > 366 * 86400) {
                jsonResponse(false, 'Período inválido (use inicio/fim em YYYY-MM-DD, até 366 dias).', [], 422);
            }
        }

        $itens = $repo->listarPeriodo($inicio, $fim);
        jsonResponse(true, 'Agenda retornada com sucesso.', [
            'inicio' => $inicio,
            'fim'    => $fim,
            'itens'  => $itens,
            'total'  => count($itens),
        ]);
    }

    if ($metodo === 'POST') {
        $evento = $repo->normalizar($payload);
        if ($evento === null) {
            jsonResponse(false, 'Informe título e data válidos (hora opcional no formato HH:MM).', [], 422);
        }
        $id = $repo->criar($evento);
        jsonResponse(true, 'Evento criado com sucesso.', ['id' => $id], 201);
    }

    if ($metodo === 'PATCH') {
        $id = trim((string)($payload['id'] ?? ''));
        if ($id === '') {
            jsonResponse(false, 'id é obrigatório.', [], 422);
        }

        // Atalho: marcar/desmarcar como concluído
        if (array_key_exists('concluido', $payload) && !array_key_exists('titulo', $payload)) {
            if (!$repo->concluir($id, (bool)$payload['concluido'])) {
                jsonResponse(false, 'Evento não encontrado.', [], 404);
            }
            jsonResponse(true, 'Evento atualizado com sucesso.');
        }

        $evento = $repo->normalizar($payload);
        if ($evento === null) {
            jsonResponse(false, 'Informe título e data válidos (hora opcional no formato HH:MM).', [], 422);
        }
        if (!$repo->atualizar($id, $evento)) {
            jsonResponse(false, 'Evento não encontrado.', [], 404);
        }
        jsonResponse(true, 'Evento atualizado com sucesso.');
    }

    if ($metodo === 'DELETE') {
        $id = trim((string)($payload['id'] ?? ''));
        if ($id === '') {
            jsonResponse(false, 'id é obrigatório.', [], 422);
        }
        if (!$repo->excluir($id)) {
            jsonResponse(false, 'Evento não encontrado.', [], 404);
        }
        jsonResponse(true, 'Evento excluído com sucesso.');
    }
} catch (\Throwable $e) {
    jsonResponse(false, 'Erro ao processar a agenda.', ['detalhe' => $e->getMessage()], 500);
}

jsonResponse(false, 'Método não permitido.', [], 405);
