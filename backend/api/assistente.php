<?php
/**
 * ENDPOINT — /backend/api/assistente.php
 *
 * GET  -> orçamento do dia (quanto posso gastar hoje), plano atual, histórico do chat e se a IA está ligada
 * POST -> { acao: "mensagem", texto } conversa com a IA | { acao: "limpar" } apaga o histórico
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../repositories/AssistenteRepository.php';
require_once __DIR__ . '/../services/AssistenteService.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = requireAuthenticatedUserId();

try {
    $repo = new AssistenteRepository($userId);
    $servico = new AssistenteService($repo);

    if ($metodo === 'GET') {
        jsonResponse(true, 'Assistente carregado.', [
            'ia_ativa'   => $servico->habilitado(),
            'orcamento'  => $repo->orcamento(),
            'plano'      => $repo->getPlano(),
            'mensagens'  => $repo->mensagensRecentes(40),
            'acoes'      => $repo->acoesRecentes(8),
        ]);
    }

    if ($metodo === 'POST') {
        $p = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $acao = strtolower(trim((string)($p['acao'] ?? 'mensagem')));

        if ($acao === 'limpar') {
            $repo->limparHistorico();
            jsonResponse(true, 'Conversa apagada.');
        }

        if ($acao === 'mensagem') {
            if (!$servico->habilitado()) {
                jsonResponse(false, 'A IA ainda não foi ativada. Adicione a chave da API em backend/config/assistente.php.', [], 503);
            }
            $texto = trim((string)($p['texto'] ?? ''));
            if ($texto === '' || mb_strlen($texto) > 2000) {
                jsonResponse(false, 'Escreva uma mensagem de até 2000 caracteres.', [], 422);
            }
            if ($repo->mensagensUltimas24h() >= $servico->limiteDiario()) {
                jsonResponse(false, 'Limite diário de mensagens da IA atingido. Volte amanhã.', [], 429);
            }

            try {
                $r = $servico->responder($texto);
            } catch (RuntimeException $e) {
                jsonResponse(false, $e->getMessage(), [], 502);
            }

            jsonResponse(true, 'Resposta gerada.', $r + [
                'orcamento' => $repo->orcamento(),
                'plano'     => $repo->getPlano(),
            ]);
        }

        jsonResponse(false, 'Ação inválida.', [], 422);
    }
} catch (\Throwable $e) {
    error_log('[assistente] ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    jsonResponse(false, 'Erro ao processar o assistente: ' . mb_substr($e->getMessage(), 0, 160), [], 500);
}

jsonResponse(false, 'Método não permitido.', [], 405);
