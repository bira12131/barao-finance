<?php
/**
 * REPOSITORY — InvestimentosRepository
 *
 * A API da Pierre NÃO informa saldo de investimentos. O que ela entrega:
 *  - bankData.automaticallyInvestedBalance e reservedBalances (saldo que rende / "caixinhas");
 *  - transações de aplicação/resgate/rendimento.
 * Por isso mostramos esses dados por conta e permitimos informar o saldo investido real.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/schema.php';

class InvestimentosRepository
{
    private PDO $pdo;
    private string $userId;

    public function __construct(string $userId)
    {
        $this->userId = trim($userId);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $this->userId)) {
            throw new InvalidArgumentException('userId inválido para InvestimentosRepository.');
        }

        $this->pdo = getConnection();
        schemaOnce($this->pdo, 'investimentos_manuais_v1', fn() => $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS investimentos_manuais (
                user_id         UUID          NOT NULL,
                pierre_conta_id VARCHAR(300)  NOT NULL,
                valor           NUMERIC(15,2) NOT NULL,
                atualizado_em   TIMESTAMPTZ   DEFAULT NOW(),
                PRIMARY KEY (user_id, pierre_conta_id)
            )'
        ));
    }

    /** Salva (ou limpa, com null) o saldo investido informado para uma conta. */
    public function salvarManual(string $contaId, ?float $valor): bool
    {
        $chk = $this->pdo->prepare("SELECT 1 FROM pierre_contas WHERE user_id = :u AND pierre_id = :c AND tipo = 'BANK'");
        $chk->execute([':u' => $this->userId, ':c' => $contaId]);
        if (!$chk->fetchColumn()) return false;

        if ($valor === null) {
            $this->pdo->prepare('DELETE FROM investimentos_manuais WHERE user_id = :u AND pierre_conta_id = :c')
                ->execute([':u' => $this->userId, ':c' => $contaId]);
            return true;
        }

        $this->pdo->prepare(
            'INSERT INTO investimentos_manuais (user_id, pierre_conta_id, valor, atualizado_em)
             VALUES (:u, :c, :v, NOW())
             ON CONFLICT (user_id, pierre_conta_id) DO UPDATE SET valor = EXCLUDED.valor, atualizado_em = NOW()'
        )->execute([':u' => $this->userId, ':c' => $contaId, ':v' => round(max(0, $valor), 2)]);
        return true;
    }

    /** Uma entrada por conta bancária, com os dados de investimento disponíveis. */
    public function porConta(): array
    {
        $contas = $this->pdo->prepare(
            "SELECT pierre_id, nome, nome_marketing, banco, subtipo, dados_raw
             FROM pierre_contas
             WHERE user_id = :u AND tipo = 'BANK' AND ativo = TRUE
             ORDER BY banco, nome"
        );
        $contas->execute([':u' => $this->userId]);

        $manuais = [];
        $m = $this->pdo->prepare('SELECT pierre_conta_id, valor::float8 AS valor, atualizado_em::text AS atualizado_em FROM investimentos_manuais WHERE user_id = :u');
        $m->execute([':u' => $this->userId]);
        foreach ($m->fetchAll() as $r) $manuais[$r['pierre_conta_id']] = $r;

        // Movimentações dos últimos 180 dias, por BANCO (contas ocultas, como a poupança do Inter,
        // somam na conta visível do mesmo banco).
        $mov = $this->pdo->prepare(
            "SELECT COALESCE(c.banco, t.conta_nome) AS banco,
                    COALESCE(SUM(ABS(t.valor)) FILTER (WHERE t.tipo = 'DEBIT'  AND LOWER(COALESCE(t.categoria,'')) LIKE '%investiment%'), 0)::float8 AS aplicado,
                    COALESCE(SUM(ABS(t.valor)) FILTER (WHERE t.tipo = 'CREDIT' AND LOWER(COALESCE(t.categoria,'')) LIKE '%investiment%'), 0)::float8 AS resgatado,
                    COALESCE(SUM(ABS(t.valor)) FILTER (WHERE t.tipo = 'CREDIT' AND LOWER(t.descricao) LIKE 'rendimento%'), 0)::float8 AS rendimentos
             FROM pierre_transacoes t
             LEFT JOIN pierre_contas c ON c.user_id = t.user_id AND c.pierre_id = t.pierre_conta_id
             WHERE t.user_id = :u AND t.data >= CURRENT_DATE - INTERVAL '180 days'
             GROUP BY 1"
        );
        $mov->execute([':u' => $this->userId]);
        $movs = [];
        foreach ($mov->fetchAll() as $r) $movs[(string)$r['banco']] = $r;

        $out = [];
        foreach ($contas->fetchAll() as $c) {
            $raw  = json_decode($c['dados_raw'] ?? '{}', true) ?: [];
            $bank = $raw['bankData'] ?? [];
            $id   = $c['pierre_id'];

            $automatico = (float)($bank['automaticallyInvestedBalance'] ?? 0);
            $reservas = [];
            $totalReservas = 0.0;
            foreach ((array)($bank['reservedBalances'] ?? []) as $rb) {
                $valor = 0.0;
                foreach ((array)($rb['availableAmounts'] ?? []) as $av) $valor += (float)($av['amount'] ?? 0);
                $totalReservas += $valor;
                $reservas[] = ['nome' => $rb['name'] ?? 'Reserva', 'valor' => round($valor, 2)];
            }

            $mv = $movs[(string)($c['banco'] ?: $c['nome'])] ?? ['aplicado' => 0, 'resgatado' => 0, 'rendimentos' => 0];
            $manual = $manuais[$id] ?? null;

            $out[] = [
                'conta_id'        => $id,
                'banco'           => $c['banco'] ?: ($c['nome_marketing'] ?: $c['nome']),
                'conta'           => $c['nome_marketing'] ?: $c['nome'],
                'subtipo'         => $c['subtipo'],
                'icone_url'       => $raw['connectorImageUrl'] ?? null,
                // Saldo que rende automaticamente (positivo). Negativo = cheque especial, não é investimento.
                'saldo_api'       => round(max(0, $automatico) + $totalReservas, 2),
                'reservas'        => $reservas,
                'cheque_especial_usado' => (float)($bank['overdraftUsedLimit'] ?? 0),
                'aplicado_180d'   => round((float)$mv['aplicado'], 2),
                'resgatado_180d'  => round((float)$mv['resgatado'], 2),
                'rendimentos_180d' => round((float)$mv['rendimentos'], 2),
                'saldo_informado' => $manual ? (float)$manual['valor'] : null,
                'informado_em'    => $manual['atualizado_em'] ?? null,
            ];
        }
        return $out;
    }
}
