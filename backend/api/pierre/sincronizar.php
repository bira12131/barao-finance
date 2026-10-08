<?php
/**
 * ENDPOINT — /backend/api/pierre/sincronizar.php
 *
 * Fluxo:
 *  POST                    -> syncAll(): busca contas + transações da Pierre e salva no PostgreSQL
 *                             (rápido; detecta assinaturas só se houver transação nova)
 *  POST ?escopo=contas     -> só contas, saldos e cartões (rápido, sem transações)
 *  POST ?somente_bancos=1  -> pede à Pierre para atualizar os bancos (manual-update, lento,
 *                             termina em segundo plano; a tela dispara sem esperar)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/pierre_guard.php';
require_once __DIR__ . '/../../services/PierreService.php';
require_once __DIR__ . '/../../repositories/PierreRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido.', [], 405);
}

try {
    $pierre = new PierreService();
    $userId = requireAuthenticatedUserId();

    $guard = pierreGuardAccess($userId);
    if (!$guard['allowed']) {
        jsonResponse(false, 'Integração Pierre vinculada a outro usuário deste ambiente.', [
            'status' => 'acesso_negado_owner',
        ], 403);
    }

    // Pedido de atualização aos bancos: a Pierre leva ~12s só para aceitar e termina em segundo plano.
    // Por isso roda numa requisição separada (a tela não espera por ela).
    if (($_GET['somente_bancos'] ?? '') === '1') {
        ignore_user_abort(true);
        set_time_limit(90);
        $pierre->sincronizar();
        jsonResponse(true, 'Atualização dos bancos solicitada.');
    }

    $repo = new PierreRepository($userId);

    // Busca contas e transações já disponíveis na Pierre e salva no banco.
    $resultado = ($_GET['escopo'] ?? '') === 'contas' ? $pierre->syncContas($repo) : $pierre->syncAll($repo);

    if (!$resultado['success'] && $resultado['status'] === 'erro') {
        jsonResponse(false, 'Erro ao sincronizar dados.', [
            'erros' => $resultado['erros'],
        ], 502);
    }

    jsonResponse(true, 'Sincronização concluída.', [
        'total_contas'      => $resultado['total_contas'],
        'total_transacoes'  => $resultado['total_transacoes'],
        'total_assinaturas' => $resultado['total_assinaturas'],
        'status'            => $resultado['status'],
        'erros'             => $resultado['erros'],
    ]);
} catch (\Throwable $e) {
    $rawError = $e->getMessage();
    $msg = 'Falha interna ao sincronizar.';
    $code = 500;

    if (stripos($rawError, 'relation') !== false && stripos($rawError, 'pierre_') !== false) {
        $msg = 'Banco não inicializado para o Pierre. Execute o SQL de criação das tabelas.';
        $code = 503;
    } elseif (stripos($rawError, 'curl_init') !== false || stripos($rawError, 'undefined function curl_') !== false) {
        $msg = 'Servidor sem extensão cURL ativa. Habilite cURL no PHP para sincronizar com o Pierre.';
        $code = 503;
    } elseif (stripos($rawError, 'Sessão inválida') !== false || stripos($rawError, 'expirada') !== false) {
        $msg = 'Sessão expirada. Faça login novamente para sincronizar.';
        $code = 401;
    }

    jsonResponse(false, $msg, [
        'erro' => $rawError,
    ], $code);
}

