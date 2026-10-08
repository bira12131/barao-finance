<?php
/**
 * Classificação de um lançamento em 'receita', 'despesa' ou 'interno' (não entra nos totais).
 *
 * Mantém a mesma regra de js/finance-rules.js e acrescenta o que só o servidor sabe: pagamento de fatura
 * feito por PIX/transferência (categoria "Transferências") tem o valor exato de uma fatura fechada do cartão,
 * e contá-lo como gasto duplica as compras do cartão, que já entram pelos itens da fatura.
 *
 * $faturas: linhas de pierre_faturas com 'total', 'fechamento' e 'vencimento' (Y-m-d).
 * $destinosIgnorados: nomes de destinatário (minúsculos) marcados pelo usuário como "não é gasto".
 */
function classeTransacao(array $tx, array $faturas = [], array $destinosIgnorados = []): string
{
    $cat   = mb_strtolower((string)($tx['category'] ?? ''));
    $desc  = mb_strtolower((string)($tx['description'] ?? ''));
    $conta = strtoupper((string)($tx['account_type'] ?? $tx['accountType'] ?? ''));
    $valor = (float)($tx['amount'] ?? 0);
    $tipo  = strtoupper((string)($tx['type'] ?? ($valor >= 0 ? 'CREDIT' : 'DEBIT')));

    // Destinatário que você marcou como "não é gasto" (em qualquer direção).
    if ($destinosIgnorados) {
        $destino = mb_strtolower(trim((string)preg_replace('/^.*\|\s*/u', '', (string)($tx['description'] ?? ''))));
        if ($destino !== '' && in_array($destino, $destinosIgnorados, true)) return 'interno';
    }

    // Crédito na conta do cartão = pagamento/ajuste de fatura, não é receita.
    if ($conta === 'CREDIT' && $tipo === 'CREDIT') return 'interno';
    if (preg_match('/pagamento de cart[aã]o|investiment|mesma titularidade|mesma institui[cç][aã]o/u', $cat)
        || preg_match('/pagamento de fatura|valor adicionado na conta por cart|aplica[cç][aã]o rdb|resgate rdb/u', $desc)) {
        return 'interno';
    }
    // Dinheiro de empréstimo entrando não é renda; as parcelas (débito) continuam sendo despesa.
    if ($tipo === 'CREDIT' && preg_match('/empr[eé]stimo/u', $cat)) return 'interno';

    // Pagamento de fatura por PIX/transferência: valor igual ao de uma fatura, entre o fechamento e 45 dias após o vencimento.
    if ($tipo === 'DEBIT' && $conta !== 'CREDIT' && $faturas) {
        $v = abs($valor);
        $dia = substr((string)($tx['date'] ?? ''), 0, 10);
        foreach ($faturas as $f) {
            if (empty($f['fechamento']) || empty($f['vencimento']) || (float)$f['total'] <= 0) continue;
            if (abs($v - (float)$f['total']) < 0.02
                && $dia >= $f['fechamento']
                && $dia <= date('Y-m-d', strtotime($f['vencimento'] . ' +45 days'))) {
                return 'interno';
            }
        }
    }

    return $tipo === 'CREDIT' ? 'receita' : 'despesa';
}
