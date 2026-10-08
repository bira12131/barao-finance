<?php
/**
 * ENDPOINT — /backend/api/emprestimos.php
 *
 * GET  -> contratos (com parcelas pagas/restantes, término) + sugestões detectadas no extrato
 * POST -> { acao: criar | editar | excluir | adiantar | remover_adiantamento, ... }  (tudo via POST: o .htaccess só libera GET/POST)
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../repositories/EmprestimosRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = requireAuthenticatedUserId();

try {
    $repo = new EmprestimosRepository($userId);

    if ($metodo === 'GET') {
        $contratos = $repo->listar();
        $ativos = array_filter($contratos, static fn($c) => !$c['quitado']);
        jsonResponse(true, 'Empréstimos retornados com sucesso.', [
            'contratos' => $contratos,
            'sugestoes' => $repo->detectar(),
            'resumo'    => [
                'parcela_mensal_total' => round(array_sum(array_column($ativos, 'valor_parcela')), 2),
                'valor_restante_total' => round(array_sum(array_column($ativos, 'valor_restante')), 2),
                'contratos_ativos'     => count($ativos),
            ],
        ]);
    }

    if ($metodo === 'POST') {
        $p    = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $acao = strtolower(trim((string)($p['acao'] ?? 'criar')));

        if ($acao === 'criar' || $acao === 'editar') {
            $dados = $repo->normalizar($p);
            if ($dados === null) {
                jsonResponse(false, 'Informe nome, valor da parcela, data da primeira parcela e total de parcelas (1 a 480).', [], 422);
            }
            if ($acao === 'criar') {
                jsonResponse(true, 'Contrato cadastrado com sucesso.', ['id' => $repo->criar($dados)], 201);
            }
            if (!$repo->atualizar(trim((string)($p['id'] ?? '')), $dados)) {
                jsonResponse(false, 'Contrato não encontrado.', [], 404);
            }
            jsonResponse(true, 'Contrato atualizado com sucesso.');
        }

        if ($acao === 'adiantar') {
            $valor = ($p['valor_pago'] ?? null) === null || $p['valor_pago'] === '' ? null : (float)$p['valor_pago'];
            $erro = $repo->adiantar(
                trim((string)($p['id'] ?? '')),
                isset($p['data']) ? (string)$p['data'] : null,
                $valor,
                (int)($p['parcelas_quitadas'] ?? 0)
            );
            if ($erro !== null) {
                jsonResponse(false, $erro, [], 422);
            }
            jsonResponse(true, 'Adiantamento registrado com sucesso.');
        }

        if ($acao === 'remover_adiantamento') {
            if (!$repo->removerAdiantamento(trim((string)($p['adiantamento_id'] ?? '')))) {
                jsonResponse(false, 'Adiantamento não encontrado.', [], 404);
            }
            jsonResponse(true, 'Adiantamento removido com sucesso.');
        }

        if ($acao === 'excluir') {
            if (!$repo->excluir(trim((string)($p['id'] ?? '')))) {
                jsonResponse(false, 'Contrato não encontrado.', [], 404);
            }
            jsonResponse(true, 'Contrato excluído com sucesso.');
        }

        jsonResponse(false, 'Ação inválida.', [], 422);
    }
} catch (\Throwable $e) {
    jsonResponse(false, 'Erro ao processar empréstimos.', ['detalhe' => $e->getMessage()], 500);
}

jsonResponse(false, 'Método não permitido.', [], 405);
