-- =============================================================
-- BARÃO FINANCE — Script de criação de tabelas
-- Banco: PostgreSQL 13+  |  Base: barao_finance
-- Execute via psql ou pelo gerenciador da KingHost
-- =============================================================

-- Extensão para geração de UUID (já disponível no PG 13+)
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- =============================================================
-- Tabela: usuarios
-- =============================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id            UUID          DEFAULT gen_random_uuid() PRIMARY KEY,
    nome          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL,
    senha_hash    VARCHAR(255)  NOT NULL,
    criado_em     TIMESTAMPTZ   DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ   DEFAULT NOW()
);

-- Índice único para e-mail (evita duplicatas e acelera busca)
CREATE UNIQUE INDEX IF NOT EXISTS idx_usuarios_email ON usuarios (email);

-- -----------------------------------------------------------------
-- Trigger: atualiza atualizado_em automaticamente em UPDATE
-- -----------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_atualizar_timestamp()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.atualizado_em = NOW();
    RETURN NEW;
END;
$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_trigger WHERE tgname = 'trg_usuarios_atualizado_em'
    ) THEN
        CREATE TRIGGER trg_usuarios_atualizado_em
            BEFORE UPDATE ON usuarios
            FOR EACH ROW EXECUTE FUNCTION fn_atualizar_timestamp();
    END IF;
END;
$$;

-- =============================================================
-- Tabela: pierre_contas — contas bancárias e cartões via Pierre Finance
-- =============================================================

-- OPCIONAL (rebuild limpo da tabela de contas):
-- Descomente este bloco apenas se quiser apagar e recriar pierre_contas.
-- IMPORTANTE: preserva pierre_transacoes, mas zera a ligação conta_id até nova sync.
-- ALTER TABLE pierre_transacoes DROP CONSTRAINT IF EXISTS pierre_transacoes_conta_id_fkey;
-- UPDATE pierre_transacoes SET conta_id = NULL;
-- DROP TABLE IF EXISTS pierre_contas;

CREATE TABLE IF NOT EXISTS pierre_contas (
    id               UUID          DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id          UUID          REFERENCES usuarios(id) ON DELETE CASCADE,
    pierre_id        VARCHAR(300)  NOT NULL,           -- ID único vindo da API Pierre
    nome             VARCHAR(250),                     -- accountName
    nome_marketing   VARCHAR(250),                     -- accountMarketingName
    banco            VARCHAR(150),                     -- providerCode / institution name
    tipo             VARCHAR(30),                      -- BANK | CREDIT | INVESTMENT | LOAN
    subtipo          VARCHAR(60),                      -- CHECKING_ACCOUNT | SAVINGS_ACCOUNT | etc.
    saldo            NUMERIC(15,2) DEFAULT 0,          -- accountBalance (contas bancárias)
    limite           NUMERIC(15,2),                    -- creditData.creditLimit (cartões)
    fatura_atual     NUMERIC(15,2),                    -- fatura corrente do cartão
    disponivel       NUMERIC(15,2),                    -- creditData.availableCreditLimit
    fechamento       DATE,                             -- creditData.balanceCloseDate
    vencimento       DATE,                             -- creditData.balanceDueDate
    bandeira         VARCHAR(60),                      -- creditData.brand (Visa, Mastercard…)
    nivel            VARCHAR(60),                      -- creditData.level (Gold, Platinum…)
    dados_raw        JSONB,                            -- payload completo da Pierre Finance
    ativo            BOOLEAN       DEFAULT TRUE,
    sincronizado_em  TIMESTAMPTZ   DEFAULT NOW(),
    criado_em        TIMESTAMPTZ   DEFAULT NOW(),
    atualizado_em    TIMESTAMPTZ   DEFAULT NOW()
);

DROP INDEX IF EXISTS idx_pierre_contas_pierre_id;
CREATE UNIQUE INDEX IF NOT EXISTS idx_pierre_contas_user_pierre_id
    ON pierre_contas (user_id, pierre_id);
CREATE INDEX IF NOT EXISTS idx_pierre_contas_user
    ON pierre_contas (user_id);

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'pierre_transacoes'
    ) THEN
        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.table_constraints
            WHERE table_schema = 'public'
              AND table_name = 'pierre_transacoes'
              AND constraint_name = 'pierre_transacoes_conta_id_fkey'
        ) THEN
            ALTER TABLE pierre_transacoes
                ADD CONSTRAINT pierre_transacoes_conta_id_fkey
                FOREIGN KEY (conta_id) REFERENCES pierre_contas(id) ON DELETE SET NULL;
        END IF;
    END IF;
END;
$$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_pierre_contas_atualizado_em') THEN
        CREATE TRIGGER trg_pierre_contas_atualizado_em
            BEFORE UPDATE ON pierre_contas
            FOR EACH ROW EXECUTE FUNCTION fn_atualizar_timestamp();
    END IF;
END;
$$;

-- =============================================================
-- Tabela: pierre_transacoes — histórico de movimentações
-- =============================================================
CREATE TABLE IF NOT EXISTS pierre_transacoes (
    id               UUID          DEFAULT gen_random_uuid() PRIMARY KEY,
    pierre_id        VARCHAR(300)  UNIQUE,             -- ID único da transação na Pierre
    pierre_conta_id  VARCHAR(300),                     -- accountId referenciando pierre_contas
    conta_id         UUID          REFERENCES pierre_contas(id) ON DELETE SET NULL,
    data             DATE          NOT NULL,
    descricao        TEXT          NOT NULL,
    valor            NUMERIC(15,2) NOT NULL,           -- positivo = crédito, negativo = débito
    tipo             VARCHAR(10)   CHECK (tipo IN ('CREDIT','DEBIT')),
    categoria        VARCHAR(150),
    conta_nome       VARCHAR(250),
    conta_tipo       VARCHAR(30),                      -- BANK | CREDIT
    status           VARCHAR(50),
    dados_raw        JSONB,
    sincronizado_em  TIMESTAMPTZ   DEFAULT NOW(),
    criado_em        TIMESTAMPTZ   DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pierre_tx_data
    ON pierre_transacoes (data DESC);
CREATE INDEX IF NOT EXISTS idx_pierre_tx_conta_id
    ON pierre_transacoes (conta_id);
CREATE INDEX IF NOT EXISTS idx_pierre_tx_tipo
    ON pierre_transacoes (tipo);
CREATE INDEX IF NOT EXISTS idx_pierre_tx_pierre_conta
    ON pierre_transacoes (pierre_conta_id);

-- =============================================================
-- Tabela: pierre_categorias_custom — categorias cadastradas manualmente
-- =============================================================
CREATE TABLE IF NOT EXISTS pierre_categorias_custom (
    id         SERIAL        PRIMARY KEY,
    nome       VARCHAR(150)  NOT NULL UNIQUE,
    criado_em  TIMESTAMPTZ   DEFAULT NOW()
);

-- =============================================================
-- Tabela: pierre_despesas_previstas — despesas planejadas e assinaturas manuais
-- =============================================================
CREATE TABLE IF NOT EXISTS pierre_despesas_previstas (
    id                UUID          PRIMARY KEY,
    descricao         TEXT          NOT NULL,
    primeira_cobranca DATE          NOT NULL,
    duracao_meses     INT,                          -- despesa prevista: obrigatório no cadastro
    valor_mensal      NUMERIC(15,2),                -- assinatura manual: sem duração, com valor mensal
    ativa             BOOLEAN       DEFAULT TRUE,
    criado_em         TIMESTAMPTZ   DEFAULT NOW()
);

ALTER TABLE pierre_despesas_previstas
    ALTER COLUMN duracao_meses DROP NOT NULL;

ALTER TABLE pierre_despesas_previstas
    ADD COLUMN IF NOT EXISTS valor_mensal NUMERIC(15,2);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'pierre_despesas_previstas_duracao_check'
    ) THEN
        ALTER TABLE pierre_despesas_previstas
            ADD CONSTRAINT pierre_despesas_previstas_duracao_check
            CHECK (duracao_meses IS NULL OR duracao_meses > 0);
    END IF;
END;
$$;

-- =============================================================
-- Tabela: pierre_assinaturas — recorrências detectadas automaticamente
-- =============================================================
CREATE TABLE IF NOT EXISTS pierre_assinaturas (
    id               UUID          DEFAULT gen_random_uuid() PRIMARY KEY,
    descricao        TEXT          NOT NULL,
    valor            NUMERIC(15,2) NOT NULL,
    periodicidade    VARCHAR(20)   DEFAULT 'MONTHLY',  -- MONTHLY | WEEKLY | QUARTERLY | YEARLY
    ultima_cobranca  DATE,
    proxima_cobranca DATE,
    categoria        VARCHAR(150),
    conta_nome       VARCHAR(250),
    conta_tipo       VARCHAR(30),
    ativa            BOOLEAN       DEFAULT TRUE,
    criado_em        TIMESTAMPTZ   DEFAULT NOW(),
    atualizado_em    TIMESTAMPTZ   DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_pierre_assin_uniq
    ON pierre_assinaturas (LOWER(descricao), valor);

-- =============================================================
-- Tabela: pierre_assinaturas_ignoradas — assinaturas marcadas como indevidas
-- =============================================================
CREATE TABLE IF NOT EXISTS pierre_assinaturas_ignoradas (
    id         SERIAL        PRIMARY KEY,
    descricao  TEXT          NOT NULL,
    valor      NUMERIC(15,2) NOT NULL,
    criado_em  TIMESTAMPTZ   DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_assin_ignoradas_desc_valor
    ON pierre_assinaturas_ignoradas (LOWER(descricao), valor);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_pierre_assin_atualizado_em') THEN
        CREATE TRIGGER trg_pierre_assin_atualizado_em
            BEFORE UPDATE ON pierre_assinaturas
            FOR EACH ROW EXECUTE FUNCTION fn_atualizar_timestamp();
    END IF;
END;
$$;

-- =============================================================
-- Tabela: pierre_sincronizacoes — log de cada sincronização
-- =============================================================
CREATE TABLE IF NOT EXISTS pierre_sincronizacoes (
    id               SERIAL        PRIMARY KEY,
    tipo             VARCHAR(50)   DEFAULT 'completo',
    status           VARCHAR(20)   DEFAULT 'sucesso',  -- sucesso | erro | parcial
    total_contas     INT           DEFAULT 0,
    total_transacoes INT           DEFAULT 0,
    total_assinaturas INT          DEFAULT 0,
    detalhes         TEXT,
    sincronizado_em  TIMESTAMPTZ   DEFAULT NOW()
);

-- =============================================================
-- Escopo por usuário (multiusuário)
-- =============================================================

ALTER TABLE pierre_contas ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE pierre_transacoes ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE pierre_categorias_custom ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE pierre_despesas_previstas ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE pierre_assinaturas ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE pierre_assinaturas_ignoradas ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE pierre_sincronizacoes ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES usuarios(id) ON DELETE CASCADE;

-- Backfill legado atual (ambiente com usuário único).
-- Garante que os registros antigos apareçam após ativar o escopo por usuário.
UPDATE pierre_contas SET user_id = '05bc68e7-92b6-422a-88de-d71a0e45319e' WHERE user_id IS NULL;
UPDATE pierre_transacoes SET user_id = '05bc68e7-92b6-422a-88de-d71a0e45319e' WHERE user_id IS NULL;
UPDATE pierre_categorias_custom SET user_id = '05bc68e7-92b6-422a-88de-d71a0e45319e' WHERE user_id IS NULL;
UPDATE pierre_despesas_previstas SET user_id = '05bc68e7-92b6-422a-88de-d71a0e45319e' WHERE user_id IS NULL;
UPDATE pierre_assinaturas SET user_id = '05bc68e7-92b6-422a-88de-d71a0e45319e' WHERE user_id IS NULL;
UPDATE pierre_assinaturas_ignoradas SET user_id = '05bc68e7-92b6-422a-88de-d71a0e45319e' WHERE user_id IS NULL;
UPDATE pierre_sincronizacoes SET user_id = '05bc68e7-92b6-422a-88de-d71a0e45319e' WHERE user_id IS NULL;

-- Deduplicação antes de criar índices únicos por usuário.
LOCK TABLE pierre_contas IN ACCESS EXCLUSIVE MODE;

WITH ranked AS (
    SELECT id,
           ROW_NUMBER() OVER (
               PARTITION BY user_id, pierre_id
               ORDER BY atualizado_em DESC NULLS LAST, sincronizado_em DESC NULLS LAST, criado_em DESC NULLS LAST, id DESC
           ) AS rn
    FROM pierre_contas
)
DELETE FROM pierre_contas c
USING ranked r
WHERE c.id = r.id
  AND r.rn > 1;

DROP INDEX IF EXISTS idx_pierre_contas_pierre_id;
DROP INDEX IF EXISTS idx_pierre_contas_user_pierre_id;
CREATE UNIQUE INDEX idx_pierre_contas_user_pierre_id ON pierre_contas (user_id, pierre_id);

WITH ranked AS (
    SELECT id,
           ROW_NUMBER() OVER (
               PARTITION BY user_id, pierre_id
               ORDER BY sincronizado_em DESC NULLS LAST, criado_em DESC NULLS LAST, id DESC
           ) AS rn
    FROM pierre_transacoes
    WHERE pierre_id IS NOT NULL
)
DELETE FROM pierre_transacoes t
USING ranked r
WHERE t.id = r.id
  AND r.rn > 1;

WITH ranked AS (
    SELECT id,
           ROW_NUMBER() OVER (
               PARTITION BY user_id, LOWER(TRIM(nome))
               ORDER BY criado_em DESC NULLS LAST, id DESC
           ) AS rn
    FROM pierre_categorias_custom
)
DELETE FROM pierre_categorias_custom c
USING ranked r
WHERE c.id = r.id
  AND r.rn > 1;

WITH ranked AS (
    SELECT id,
           ROW_NUMBER() OVER (
               PARTITION BY user_id, LOWER(TRIM(descricao)), ROUND(valor::numeric, 2)
               ORDER BY atualizado_em DESC NULLS LAST, criado_em DESC NULLS LAST, id DESC
           ) AS rn
    FROM pierre_assinaturas
)
DELETE FROM pierre_assinaturas a
USING ranked r
WHERE a.id = r.id
  AND r.rn > 1;

WITH ranked AS (
    SELECT id,
           ROW_NUMBER() OVER (
               PARTITION BY user_id, LOWER(TRIM(descricao)), ROUND(valor::numeric, 2)
               ORDER BY criado_em DESC NULLS LAST, id DESC
           ) AS rn
    FROM pierre_assinaturas_ignoradas
)
DELETE FROM pierre_assinaturas_ignoradas i
USING ranked r
WHERE i.id = r.id
  AND r.rn > 1;

ALTER TABLE pierre_transacoes DROP CONSTRAINT IF EXISTS pierre_transacoes_pierre_id_key;
CREATE UNIQUE INDEX IF NOT EXISTS idx_pierre_tx_user_pierre_id
    ON pierre_transacoes (user_id, pierre_id)
    WHERE pierre_id IS NOT NULL;

ALTER TABLE pierre_categorias_custom DROP CONSTRAINT IF EXISTS pierre_categorias_custom_nome_key;
CREATE UNIQUE INDEX IF NOT EXISTS idx_categorias_custom_nome_unique
    ON pierre_categorias_custom (user_id, LOWER(nome));

DROP INDEX IF EXISTS idx_pierre_assin_uniq;
CREATE UNIQUE INDEX IF NOT EXISTS idx_pierre_assin_uniq
    ON pierre_assinaturas (user_id, LOWER(descricao), valor);

DROP INDEX IF EXISTS idx_assin_ignoradas_desc_valor;
CREATE UNIQUE INDEX IF NOT EXISTS idx_assin_ignoradas_desc_valor
    ON pierre_assinaturas_ignoradas (user_id, LOWER(descricao), valor);

CREATE INDEX IF NOT EXISTS idx_pierre_contas_user ON pierre_contas (user_id);
CREATE INDEX IF NOT EXISTS idx_pierre_tx_user ON pierre_transacoes (user_id);
CREATE INDEX IF NOT EXISTS idx_pierre_sync_user ON pierre_sincronizacoes (user_id);
CREATE INDEX IF NOT EXISTS idx_pierre_despesas_user ON pierre_despesas_previstas (user_id);

-- =============================================================
-- Tabela: agenda_eventos — compromissos, lembretes e tarefas
-- (também criada automaticamente por AgendaRepository)
-- =============================================================
CREATE TABLE IF NOT EXISTS agenda_eventos (
    id            UUID         DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id       UUID         NOT NULL,
    titulo        TEXT         NOT NULL,
    descricao     TEXT,
    tipo          VARCHAR(20)  NOT NULL DEFAULT 'compromisso',  -- compromisso | lembrete | tarefa
    data_inicio   DATE         NOT NULL,
    hora          TIME,
    recorrencia   VARCHAR(20)  NOT NULL DEFAULT 'nenhuma',      -- nenhuma | semanal | mensal | anual
    concluido     BOOLEAN      NOT NULL DEFAULT FALSE,
    criado_em     TIMESTAMPTZ  DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ  DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_agenda_user_data ON agenda_eventos (user_id, data_inicio);

-- =============================================================
-- Metas financeiras (também criadas automaticamente por MetasRepository)
-- =============================================================
CREATE TABLE IF NOT EXISTS metas (
    id            UUID          DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id       UUID          NOT NULL,
    nome          TEXT          NOT NULL,
    componentes   JSONB         NOT NULL DEFAULT '[]'::jsonb,   -- [{nome, valor}]
    valor_alvo    NUMERIC(15,2) NOT NULL,
    valor_inicial NUMERIC(15,2) NOT NULL DEFAULT 0,
    data_alvo     DATE,
    status        VARCHAR(20)   NOT NULL DEFAULT 'ativa',
    criado_em     TIMESTAMPTZ   DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ   DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_metas_user ON metas (user_id);

CREATE TABLE IF NOT EXISTS metas_aportes (
    id        UUID          DEFAULT gen_random_uuid() PRIMARY KEY,
    meta_id   UUID          NOT NULL,
    user_id   UUID          NOT NULL,
    valor     NUMERIC(15,2) NOT NULL,                           -- negativo = retirada
    data      DATE          NOT NULL DEFAULT CURRENT_DATE,
    criado_em TIMESTAMPTZ   DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_metas_aportes_meta ON metas_aportes (meta_id);

CREATE TABLE IF NOT EXISTS perfil_financeiro (
    user_id       UUID          PRIMARY KEY,
    renda_mensal  NUMERIC(15,2),
    atualizado_em TIMESTAMPTZ   DEFAULT NOW()
);
