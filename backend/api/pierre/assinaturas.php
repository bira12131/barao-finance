<?php
/**
 * ENDPOINT — /backend/api/pierre/assinaturas.php
 *
 * Retorna assinaturas recorrentes detectadas automaticamente
 * a partir do histórico de transações armazenado no banco.
 *
 * A detecção usa: transações DEBIT com mesma descrição + valor,
 * ≥ 2 ocorrências nos últimos 120 dias, agrupando por intervalo
 * médio para determinar WEEKLY / MONTHLY / QUARTERLY / YEARLY.
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/pierre_guard.php';
require_once __DIR__ . '/../../repositories/PierreRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$userId = requireAuthenticatedUserId();
$guard = pierreGuardAccess($userId);
if (!$guard['allowed']) {
    jsonResponse(false, 'Dados Pierre indisponíveis para este usuário.', [
        'status' => 'acesso_negado_owner',
    ], 403);
}

$repo = new PierreRepository($userId);

if ($metodo === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $acao = strtolower(trim((string)($payload['acao'] ?? '')));

    if ($acao === 'cadastrar_manual') {
        $descricao = trim((string)($payload['descricao'] ?? ''));
        $primeiraCobrancaMes = trim((string)($payload['primeira_cobranca_mes'] ?? ''));
        $valor = (float)($payload['valor'] ?? 0);

        if ($descricao === '' || $primeiraCobrancaMes === '' || $valor <= 0) {
            jsonResponse(false, 'descricao, primeira_cobranca_mes e valor são obrigatórios.', [], 422);
        }

        $ok = $repo->criarAssinaturaManual($descricao, $primeiraCobrancaMes, $valor);
        if (!$ok) {
            jsonResponse(false, 'Não foi possível cadastrar assinatura manual.', [], 422);
        }

        jsonResponse(true, 'Assinatura manual cadastrada com sucesso.');
    }

    if ($acao === 'status_manual') {
        $id = trim((string)($payload['id'] ?? ''));
        $ativa = filter_var($payload['ativa'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($id === '' || $ativa === null) {
            jsonResponse(false, 'id e ativa são obrigatórios.', [], 422);
        }

        $ok = $repo->atualizarStatusAssinaturaManual($id, $ativa);
        if (!$ok) {
            jsonResponse(false, 'Assinatura manual não encontrada.', [], 404);
        }

        jsonResponse(true, 'Status da assinatura manual atualizado com sucesso.');
    }

    if ($acao === 'indevida') {
        $descricao = trim((string)($payload['descricao'] ?? ''));
        $valor     = (float)($payload['valor'] ?? 0);

        if ($descricao === '' || $valor <= 0) {
            jsonResponse(false, 'descricao e valor são obrigatórios.', [], 422);
        }

        $ok = $repo->marcarAssinaturaIndevida($descricao, $valor);
        if (!$ok) {
            jsonResponse(false, 'Não foi possível marcar como indevida.', [], 500);
        }

        jsonResponse(true, 'Assinatura marcada como indevida.', [
            'descricao' => $descricao,
            'valor'     => round($valor, 2),
        ]);
    }

    jsonResponse(false, 'Ação inválida.', [], 422);
}

if ($metodo !== 'GET') {
    jsonResponse(false, 'Método não permitido.', [], 405);
}

// Re-detecta assinaturas para garantir dados atualizados
try {
    $repo->detectarAssinaturas();
} catch (\Exception $e) {
    // Ignora erro na detecção — retorna o que estiver salvo
}

$assinaturas = $repo->getAssinaturas();
$assinaturasManuais = $repo->getAssinaturasManuais(true);

if (!empty($assinaturasManuais)) {
    $assinaturas = array_merge($assinaturas, $assinaturasManuais);
}

// Formata para o frontend
$resultado = array_map(function ($a) {
    $periodicidadeLabel = [
        'WEEKLY'    => 'Semanal',
        'MONTHLY'   => 'Mensal',
        'QUARTERLY' => 'Trimestral',
        'YEARLY'    => 'Anual',
    ][$a['periodicidade']] ?? $a['periodicidade'];

    return [
        'id'               => $a['id'],
        'descricao'        => ucfirst($a['descricao']),
        'valor'            => (float) $a['valor'],
        'periodicidade'    => $a['periodicidade'],
        'periodicidade_label' => $periodicidadeLabel,
        'ultima_cobranca'  => $a['ultima_cobranca'],
        'proxima_cobranca' => $a['proxima_cobranca'],
        'categoria'        => $a['categoria'],
        'conta_nome'       => $a['conta_nome'],
        'conta_tipo'       => $a['conta_tipo'],
        'ativa'            => (bool) $a['ativa'],
        'criado_em'        => $a['criado_em'],
        'origem'           => $a['origem'] ?? 'detectada',
    ];
}, $assinaturas);

// Retorna apenas assinaturas atuais (com próxima cobrança hoje ou futura).
$hoje = date('Y-m-d');
$resultado = array_values(array_filter($resultado, function ($a) use ($hoje) {
    $proxima = substr((string)($a['proxima_cobranca'] ?? ''), 0, 10);
    if ($proxima === '') return false;
    return $proxima >= $hoje;
}));

// Total mensal estimado (apenas assinaturas mensais e semanais)
$totalMensal = array_sum(array_map(function ($a) {
    switch ($a['periodicidade']) {
        case 'WEEKLY':    return $a['valor'] * 4.33;
        case 'MONTHLY':   return $a['valor'];
        case 'QUARTERLY': return $a['valor'] / 3;
        case 'YEARLY':    return $a['valor'] / 12;
        default:          return $a['valor'];
    }
}, $resultado));

jsonResponse(true, 'Assinaturas retornadas com sucesso.', [
    'assinaturas'  => $resultado,
    'total'        => count($resultado),
    'total_mensal' => round($totalMensal, 2),
]);
