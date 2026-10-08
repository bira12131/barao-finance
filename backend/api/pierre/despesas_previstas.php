<?php
/**
 * ENDPOINT — /backend/api/pierre/despesas_previstas.php
 *
 * GET    -> lista despesas previstas
 * POST   -> cadastra despesa prevista
 * PATCH  -> edita despesa prevista   (ou POST + _method=PATCH)
 * DELETE -> exclui despesa prevista  (ou POST + _method=DELETE)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../repositories/PierreRepository.php';
require_once __DIR__ . '/../../repositories/EmprestimosRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// O servidor (.htaccess) só libera GET/POST/OPTIONS: PATCH e DELETE chegam via POST + _method.
if ($metodo === 'POST') {
    $corpo = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $override = strtoupper((string)($corpo['_method'] ?? ($_GET['_method'] ?? '')));
    if (in_array($override, ['PATCH', 'DELETE'], true)) {
        $metodo = $override;
    }
}

$userId = requireAuthenticatedUserId();
$repo   = new PierreRepository($userId);

if ($metodo === 'GET') {
    $apenasAtivas = !isset($_GET['ativas']) || $_GET['ativas'] !== '0';
    $mesReferencia = trim((string)($_GET['mes_referencia'] ?? ''));
    $mesFiltro = $mesReferencia !== '' ? $mesReferencia : null;
    $despesas = $repo->getDespesasPrevistas($apenasAtivas, $mesFiltro);

    // Itens automáticos: faturas de cartão, assinaturas e parcelas de empréstimo (sem cadastro manual).
    if ($apenasAtivas && ($_GET['faturas'] ?? '1') !== '0') {
        $mesAuto = $mesFiltro ?? date('Y-m');
        $automaticas = [];

        try { $automaticas = array_merge($automaticas, $repo->getFaturasComoDespesasPrevistas($mesFiltro)); } catch (\Throwable $e) {}
        try { $automaticas = array_merge($automaticas, $repo->getAssinaturasComoDespesasPrevistas($mesFiltro)); } catch (\Throwable $e) {}
        try {
            foreach ((new EmprestimosRepository($userId))->parcelasDoMes($mesAuto) as $p) {
                $automaticas[] = [
                    'id'                    => 'emp:' . $p['id'] . ':' . $mesAuto,
                    'descricao'             => 'Empréstimo ' . $p['nome'] . ' (parcela ' . $p['parcela'] . '/' . $p['total'] . ')',
                    'primeira_cobranca'     => $p['data'],
                    'duracao_meses'         => 1,
                    'valor_parcela'         => $p['valor'],
                    'ativa'                 => true,
                    'encerramento_previsto' => $p['data'],
                    'origem'                => 'emprestimo',
                    'somente_leitura'       => true,
                ];
            }
        } catch (\Throwable $e) {}

        $despesas = array_merge($automaticas, $despesas);
    }

    jsonResponse(true, 'Despesas previstas retornadas com sucesso.', [
        'despesas_previstas' => $despesas,
        'total'              => count($despesas),
    ]);
}

if ($metodo === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];

    $descricao            = trim((string)($payload['descricao'] ?? ''));
    $primeiraCobrancaMes  = trim((string)($payload['primeira_cobranca_mes'] ?? ''));
    $duracaoMeses         = (int)($payload['duracao_meses'] ?? 0);
    $valorParcela         = (float)($payload['valor_parcela'] ?? 0);

    if ($descricao === '' || $primeiraCobrancaMes === '' || $duracaoMeses <= 0 || $valorParcela <= 0) {
        jsonResponse(false, 'descricao, primeira_cobranca_mes, duracao_meses e valor_parcela são obrigatórios.', [], 422);
    }

    try {
        $ok = $repo->criarDespesaPrevista($descricao, $primeiraCobrancaMes, $duracaoMeses, $valorParcela);

        if (!$ok) {
            jsonResponse(false, 'Não foi possível cadastrar. Use mês no formato YYYY-MM (ex.: 2026-05), duração maior que 0 e valor da parcela maior que 0.', [
                'entrada' => [
                    'descricao'            => $descricao,
                    'primeira_cobranca_mes' => $primeiraCobrancaMes,
                    'duracao_meses'        => $duracaoMeses,
                    'valor_parcela'        => $valorParcela,
                ],
            ], 422);
        }

        jsonResponse(true, 'Despesa prevista cadastrada com sucesso.');
    } catch (\Exception $e) {
        jsonResponse(false, 'Erro ao cadastrar despesa prevista.', [
            'detalhe' => $e->getMessage(),
        ], 500);
    }
}

if ($metodo === 'PATCH') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];

    $id                   = trim((string)($payload['id'] ?? ''));
    $descricao            = trim((string)($payload['descricao'] ?? ''));
    $primeiraCobrancaMes  = trim((string)($payload['primeira_cobranca_mes'] ?? ''));
    $duracaoMeses         = (int)($payload['duracao_meses'] ?? 0);
    $valorParcela         = (float)($payload['valor_parcela'] ?? 0);

    if ($id === '' || $descricao === '' || $primeiraCobrancaMes === '' || $duracaoMeses <= 0 || $valorParcela <= 0) {
        jsonResponse(false, 'id, descricao, primeira_cobranca_mes, duracao_meses e valor_parcela são obrigatórios.', [], 422);
    }

    try {
        $ok = $repo->atualizarDespesaPrevista($id, $descricao, $primeiraCobrancaMes, $duracaoMeses, $valorParcela);

        if (!$ok) {
            jsonResponse(false, 'Despesa prevista não encontrada para edição.', [], 404);
        }

        jsonResponse(true, 'Despesa prevista atualizada com sucesso.');
    } catch (\Exception $e) {
        jsonResponse(false, 'Erro ao atualizar despesa prevista.', [
            'detalhe' => $e->getMessage(),
        ], 500);
    }
}

if ($metodo === 'DELETE') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = trim((string)($payload['id'] ?? ''));

    if ($id === '') {
        jsonResponse(false, 'id é obrigatório.', [], 422);
    }

    try {
        $ok = $repo->excluirDespesaPrevista($id);
        if (!$ok) {
            jsonResponse(false, 'Despesa prevista não encontrada para exclusão.', [], 404);
        }

        jsonResponse(true, 'Despesa prevista excluída com sucesso.');
    } catch (\Exception $e) {
        jsonResponse(false, 'Erro ao excluir despesa prevista.', [
            'detalhe' => $e->getMessage(),
        ], 500);
    }
}

jsonResponse(false, 'Método não permitido.', [], 405);
