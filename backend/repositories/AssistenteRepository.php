<?php
/**
 * REPOSITORY — AssistenteRepository
 *
 * Plano financeiro definido na conversa com a IA (quanto guardar/quitar por mês), histórico do chat,
 * orçamento diário calculado e o resumo financeiro do usuário que vai de contexto para o modelo.
 * Todas as contas são feitas aqui, em PHP: o modelo recebe os números prontos e não faz cálculo.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/schema.php';
require_once __DIR__ . '/MetasRepository.php';
require_once __DIR__ . '/EmprestimosRepository.php';
require_once __DIR__ . '/InvestimentosRepository.php';

class AssistenteRepository
{
    private PDO $pdo;
    private string $userId;

    public function __construct(string $userId)
    {
        $this->userId = trim($userId);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $this->userId)) {
            throw new InvalidArgumentException('userId inválido para AssistenteRepository.');
        }

        $this->pdo = getConnection();
        schemaOnce($this->pdo, 'assistente_v1', fn() => $this->ensureTables());
        schemaOnce($this->pdo, 'assistente_acoes_v1', fn() => $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS assistente_acoes (
                id         BIGSERIAL    PRIMARY KEY,
                user_id    UUID         NOT NULL,
                canal      VARCHAR(12)  NOT NULL,
                ferramenta VARCHAR(60)  NOT NULL,
                entrada    TEXT,
                ok         BOOLEAN      NOT NULL,
                resultado  TEXT,
                criado_em  TIMESTAMPTZ  DEFAULT NOW()
            )'
        ));
    }

    public function userId(): string
    {
        return $this->userId;
    }

    private function ensureTables(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS assistente_plano (
                user_id        UUID          PRIMARY KEY,
                guardar_mensal NUMERIC(15,2) NOT NULL DEFAULT 0,
                estrategia     TEXT,
                atualizado_em  TIMESTAMPTZ   DEFAULT NOW()
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS assistente_mensagens (
                id        BIGSERIAL    PRIMARY KEY,
                user_id   UUID         NOT NULL,
                papel     VARCHAR(10)  NOT NULL,
                conteudo  TEXT         NOT NULL,
                criado_em TIMESTAMPTZ  DEFAULT NOW()
            )'
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_assistente_msg_user ON assistente_mensagens (user_id, id)');
    }

    /* ════════════════════════════════════════════
       Plano
    ════════════════════════════════════════════ */

    public function getPlano(): array
    {
        $stmt = $this->pdo->prepare('SELECT guardar_mensal::float8 AS guardar_mensal, estrategia, atualizado_em FROM assistente_plano WHERE user_id = :u');
        $stmt->execute([':u' => $this->userId]);
        $r = $stmt->fetch();
        return $r ?: ['guardar_mensal' => 0.0, 'estrategia' => null, 'atualizado_em' => null];
    }

    public function salvarPlano(float $guardarMensal, ?string $estrategia): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO assistente_plano (user_id, guardar_mensal, estrategia, atualizado_em)
             VALUES (:u, :g, :e, NOW())
             ON CONFLICT (user_id) DO UPDATE
             SET guardar_mensal = EXCLUDED.guardar_mensal, estrategia = EXCLUDED.estrategia, atualizado_em = NOW()'
        );
        $stmt->execute([':u' => $this->userId, ':g' => round($guardarMensal, 2), ':e' => $estrategia]);
    }

    /* ════════════════════════════════════════════
       Histórico do chat
    ════════════════════════════════════════════ */

    public function adicionarMensagem(string $papel, string $conteudo): void
    {
        if (!in_array($papel, ['user', 'assistant'], true)) return;
        $this->pdo->prepare('INSERT INTO assistente_mensagens (user_id, papel, conteudo) VALUES (:u, :p, :c)')
            ->execute([':u' => $this->userId, ':p' => $papel, ':c' => $conteudo]);
    }

    /** Últimas mensagens, da mais antiga para a mais nova. */
    public function mensagensRecentes(int $limite = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT papel, conteudo, criado_em FROM (
                SELECT id, papel, conteudo, criado_em FROM assistente_mensagens WHERE user_id = :u ORDER BY id DESC LIMIT :l
             ) t ORDER BY id ASC'
        );
        $stmt->bindValue(':u', $this->userId);
        $stmt->bindValue(':l', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function mensagensUltimas24h(): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM assistente_mensagens WHERE user_id = :u AND papel = 'user' AND criado_em > NOW() - INTERVAL '24 hours'"
        );
        $stmt->execute([':u' => $this->userId]);
        return (int)$stmt->fetchColumn();
    }

    public function limparHistorico(): void
    {
        $this->pdo->prepare('DELETE FROM assistente_mensagens WHERE user_id = :u')->execute([':u' => $this->userId]);
    }

    /* ════════════════════════════════════════════
       Auditoria das ações executadas pela IA
    ════════════════════════════════════════════ */

    public function registrarAcao(string $canal, string $ferramenta, $entrada, bool $ok, string $resultado): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO assistente_acoes (user_id, canal, ferramenta, entrada, ok, resultado)
                 VALUES (:u, :c, :f, :e, :ok, :r)'
            );
            $stmt->bindValue(':u', $this->userId);
            $stmt->bindValue(':c', mb_substr($canal, 0, 12));
            $stmt->bindValue(':f', mb_substr($ferramenta, 0, 60));
            $stmt->bindValue(':e', mb_substr((string)json_encode($entrada, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), 0, 1500));
            $stmt->bindValue(':ok', $ok, PDO::PARAM_BOOL);
            $stmt->bindValue(':r', mb_substr($resultado, 0, 1500));
            $stmt->execute();
        } catch (\Throwable $e) {
            error_log('[assistente] auditoria: ' . $e->getMessage());
        }
    }

    public function acoesRecentes(int $limite = 10): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT canal, ferramenta, ok, resultado, criado_em::text AS criado_em
                 FROM assistente_acoes WHERE user_id = :u ORDER BY id DESC LIMIT :l'
            );
            $stmt->bindValue(':u', $this->userId);
            $stmt->bindValue(':l', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /* ════════════════════════════════════════════
       Orçamento do dia
    ════════════════════════════════════════════ */

    /**
     * Quanto pode gastar por dia até o fim do mês:
     *   teto do mês = renda − quanto separar por mês (plano)
     *   por dia     = (teto − gasto do mês até ontem) ÷ dias restantes (contando hoje)
     *   sobra hoje  = por dia − gasto de hoje
     */
    public function orcamento(?MetasRepository $metas = null): array
    {
        $metas ??= new MetasRepository($this->userId);
        $renda  = $metas->getRendaMensal();
        $plano  = $this->getPlano();
        $guardar = (float)$plano['guardar_mensal'];

        $hoje = new DateTimeImmutable('today');
        $diasNoMes = (int)$hoje->format('t');
        $diasRestantes = $diasNoMes - (int)$hoje->format('j') + 1;

        if ($renda === null || $renda <= 0) {
            return [
                'renda_definida' => false,
                'renda_mensal' => $renda,
                'guardar_mensal' => $guardar,
                'dias_restantes' => $diasRestantes,
            ];
        }

        $gasto = $metas->gastoDoMes();
        $teto = round($renda - $guardar, 2);
        $gastoAteOntem = round($gasto['total'] - $gasto['hoje'], 2);
        $restanteInicioDia = round($teto - $gastoAteOntem, 2);
        $porDia = $restanteInicioDia > 0 ? round($restanteInicioDia / $diasRestantes, 2) : 0.0;

        return [
            'renda_definida'    => true,
            'renda_mensal'      => $renda,
            'guardar_mensal'    => $guardar,
            'teto_mes'          => $teto,
            'gasto_mes'         => $gasto['total'],
            'gasto_hoje'        => $gasto['hoje'],
            'emprestimos_mes'   => $gasto['emprestimos_mes'],
            'restante_mes'      => round($teto - $gasto['total'], 2),
            'dias_restantes'    => $diasRestantes,
            'limite_por_dia'    => $porDia,
            'sobra_hoje'        => round($porDia - $gasto['hoje'], 2),
            'estourou_mes'      => $gasto['total'] > $teto,
            'categorias_mes'    => $gasto['categorias'],
        ];
    }

    /* ════════════════════════════════════════════
       Contexto financeiro para o modelo
    ════════════════════════════════════════════ */

    /** Cartões de crédito ativos: limite, fatura, disponível e datas (dados da última sincronização). */
    public function cartoes(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(NULLIF(nome_marketing, ''), nome) AS nome, banco, limite::float8 AS limite,
                        fatura_atual::float8 AS fatura_atual, disponivel::float8 AS disponivel,
                        fechamento::text AS fechamento, vencimento::text AS vencimento, sincronizado_em::text AS atualizado_em
                 FROM pierre_contas
                 WHERE user_id = :u AND ativo = TRUE AND tipo = 'CREDIT'
                 ORDER BY nome"
            );
            $stmt->execute([':u' => $this->userId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Contas bancárias ativas com saldo (dados da última sincronização). */
    public function contasBancarias(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(NULLIF(nome_marketing, ''), nome) AS nome, banco, saldo::float8 AS saldo, sincronizado_em::text AS atualizado_em
                 FROM pierre_contas WHERE user_id = :u AND ativo = TRUE AND tipo = 'BANK' ORDER BY banco, nome"
            );
            $stmt->execute([':u' => $this->userId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Resumo do usuário. Sem senhas, cofre, tokens nem dados brutos da Pierre. */
    public function contexto(): array
    {
        $metas = new MetasRepository($this->userId);
        $visao = $metas->visaoGeral();
        $orcamento = $this->orcamento($metas);

        $cartoes = $this->cartoes();

        $emprestimos = [];
        try {
            foreach ((new EmprestimosRepository($this->userId))->listar() as $c) {
                if ($c['quitado']) continue;
                $emprestimos[] = [
                    'id' => $c['id'], 'nome' => $c['nome'], 'credor' => $c['credor'], 'valor_parcela' => $c['valor_parcela'],
                    'parcelas_restantes' => $c['parcelas_restantes'], 'valor_restante' => $c['valor_restante'],
                    'proxima_parcela' => $c['proxima_parcela'], 'termina_em' => $c['ultima_parcela'],
                ];
            }
        } catch (\Throwable $e) {
            // sem empréstimos
        }

        $assinaturas = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT descricao, valor::float8 AS valor, periodicidade, proxima_cobranca::text AS proxima_cobranca
                 FROM pierre_assinaturas WHERE user_id = :u AND ativa = TRUE ORDER BY valor DESC LIMIT 30"
            );
            $stmt->execute([':u' => $this->userId]);
            $assinaturas = $stmt->fetchAll();
        } catch (\Throwable $e) {
            // sem assinaturas
        }

        $recentes = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT data::text AS data, descricao, ABS(valor)::float8 AS valor, categoria, conta_nome
                 FROM pierre_transacoes
                 WHERE user_id = :u AND tipo = 'DEBIT' AND data >= CURRENT_DATE - 14
                 ORDER BY data DESC, criado_em DESC LIMIT 40"
            );
            $stmt->execute([':u' => $this->userId]);
            $recentes = $stmt->fetchAll();
        } catch (\Throwable $e) {
            // sem transações
        }

        $contasBancarias = $this->contasBancarias();

        $investimentos = [];
        try {
            foreach ((new InvestimentosRepository($this->userId))->porConta() as $c) {
                $investimentos[] = [
                    'conta_id' => $c['conta_id'], 'banco' => $c['banco'], 'conta' => $c['conta'],
                    'saldo_investido' => $c['saldo_informado'] !== null ? $c['saldo_informado'] : $c['saldo_api'],
                ];
            }
        } catch (\Throwable $e) {
            // sem investimentos
        }

        $metasResumo = array_map(static fn($m) => [
            'id' => $m['id'], 'nome' => $m['nome'], 'valor_alvo' => $m['valor_alvo'] ?? null, 'data_alvo' => $m['data_alvo'] ?? null,
            'por_mes' => $m['por_mes'] ?? null,
        ], $visao['metas']);

        $a = $visao['analise'];

        return [
            'hoje' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'dia_da_semana' => ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'][(int)date('w')],
            'orcamento_do_mes' => $orcamento,
            'plano_atual' => $this->getPlano(),
            'media_mensal' => [
                'gasto_medio' => $a['gasto_medio_mensal'], 'meses_considerados' => $a['meses_considerados'],
                'capacidade_mensal' => $a['capacidade_mensal'], 'top_categorias' => $a['top_categorias'],
                'investido_total' => $a['investido_total'],
            ],
            'contas_bancarias' => $contasBancarias,
            'investimentos' => $investimentos,
            'cartoes' => $cartoes,
            'emprestimos' => $emprestimos,
            'assinaturas_ativas' => $assinaturas,
            'metas' => $metasResumo,
            'gastos_ultimos_14_dias' => $recentes,
        ];
    }
}
