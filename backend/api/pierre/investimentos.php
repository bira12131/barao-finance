<?php
/**
 * ENDPOINT — /backend/api/pierre/investimentos.php
 *
 * GET  -> investimentos por conta (dados da Pierre + saldo informado)
 * POST -> { acao: 'salvar', conta_id, valor }  (valor vazio/null limpa o saldo informado)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/pierre_guard.php';
require_once __DIR__ . '/../../repositories/InvestimentosRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = requireAuthenticatedUserId();

$guard = pierreGuardAccess($userId);
if (!$guard['allowed']) {
    jsonResponse(false, 'Dados Pierre indisponíveis para este usuário.', ['status' => 'acesso_negado_owner'], 403);
}

try {
    $repo = new InvestimentosRepository($userId);

    if ($metodo === 'GET') {
        $contas = $repo->porConta();
        $total = 0.0;
        foreach ($contas as $c) {
            $total += $c['saldo_informado'] !== null ? $c['saldo_informado'] : $c['saldo_api'];
        }
        jsonResponse(true, 'Investimentos retornados com sucesso.', [
            'contas' => $contas,
            'total'  => round($total, 2),
        ]);
    }

    if ($metodo === 'POST') {
        $p = json_decode((string)file_get_contents('php://input'), true) ?: [];
        if (($p['acao'] ?? '') !== 'salvar') {
            jsonResponse(false, 'Ação inválida.', [], 422);
        }
        $contaId = trim((string)($p['conta_id'] ?? ''));
        $valor   = ($p['valor'] ?? null) === null || $p['valor'] === '' ? null : (float)$p['valor'];
        if ($contaId === '' || ($valor !== null && $valor < 0)) {
            jsonResponse(false, 'Informe a conta e um valor válido (0 ou mais).', [], 422);
        }
        if (!$repo->salvarManual($contaId, $valor)) {
            jsonResponse(false, 'Conta não encontrada.', [], 404);
        }
        jsonResponse(true, 'Saldo investido atualizado.');
    }
} catch (\Throwable $e) {
    jsonResponse(false, 'Erro ao processar investimentos.', ['detalhe' => $e->getMessage()], 500);
}

jsonResponse(false, 'Método não permitido.', [], 405);
