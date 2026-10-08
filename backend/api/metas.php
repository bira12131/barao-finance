<?php
/**
 * ENDPOINT — /backend/api/metas.php
 *
 * GET  -> metas calculadas (quanto guardar por mês), renda e análise de gastos
 * POST -> { acao: criar | editar | excluir | aporte | renda | config | ignorar_destino | desfazer_destino, ... }
 *         (tudo via POST: o .htaccess só libera GET/POST/OPTIONS)
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../repositories/MetasRepository.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = requireAuthenticatedUserId();

try {
    $repo = new MetasRepository($userId);

    if ($metodo === 'GET') {
        jsonResponse(true, 'Metas retornadas com sucesso.', $repo->visaoGeral());
    }

    if ($metodo === 'POST') {
        $p    = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $acao = strtolower(trim((string)($p['acao'] ?? 'criar')));

        if ($acao === 'criar' || $acao === 'editar') {
            $meta = $repo->normalizar($p);
            if ($meta === null) {
                jsonResponse(false, 'Informe um nome, um valor maior que zero e um prazo válido (ou deixe o prazo em branco).', [], 422);
            }
            if ($acao === 'criar') {
                jsonResponse(true, 'Meta criada com sucesso.', ['id' => $repo->criar($meta)], 201);
            }
            if (!$repo->atualizar(trim((string)($p['id'] ?? '')), $meta)) {
                jsonResponse(false, 'Meta não encontrada.', [], 404);
            }
            jsonResponse(true, 'Meta atualizada com sucesso.');
        }

        if ($acao === 'excluir') {
            if (!$repo->excluir(trim((string)($p['id'] ?? '')))) {
                jsonResponse(false, 'Meta não encontrada.', [], 404);
            }
            jsonResponse(true, 'Meta excluída com sucesso.');
        }

        if ($acao === 'aporte') {
            $valor = (float)($p['valor'] ?? 0);
            if (!$repo->aportar(trim((string)($p['id'] ?? '')), $valor)) {
                jsonResponse(false, 'Não foi possível registrar. Verifique o valor (uma retirada não pode passar do que já foi guardado).', [], 422);
            }
            jsonResponse(true, 'Aporte registrado com sucesso.');
        }

        if ($acao === 'ignorar_destino' || $acao === 'desfazer_destino') {
            $destino = (string)($p['destino'] ?? '');
            $ok = $acao === 'ignorar_destino' ? $repo->ignorarDestino($destino) : $repo->desfazerDestino($destino);
            if (!$ok) {
                jsonResponse(false, 'Não foi possível atualizar esse destinatário.', [], 422);
            }
            jsonResponse(true, $acao === 'ignorar_destino' ? 'Marcado como "não é gasto".' : 'Voltou a contar como gasto.');
        }

        if ($acao === 'config') {
            $repo->salvarContarTransferencias(filter_var($p['contar_transferencias'] ?? true, FILTER_VALIDATE_BOOLEAN));
            jsonResponse(true, 'Configuração atualizada com sucesso.');
        }

        if ($acao === 'renda') {
            $renda = $p['renda_mensal'] ?? null;
            if ($renda === null || $renda === '') {
                $repo->salvarRendaMensal(null);
            } else {
                $renda = (float)$renda;
                if ($renda < 0) {
                    jsonResponse(false, 'A renda não pode ser negativa.', [], 422);
                }
                $repo->salvarRendaMensal(round($renda, 2));
            }
            jsonResponse(true, 'Renda atualizada com sucesso.');
        }

        jsonResponse(false, 'Ação inválida.', [], 422);
    }
} catch (\Throwable $e) {
    jsonResponse(false, 'Erro ao processar metas.', ['detalhe' => $e->getMessage()], 500);
}

jsonResponse(false, 'Método não permitido.', [], 405);
