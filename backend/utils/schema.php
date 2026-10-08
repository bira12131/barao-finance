<?php
/**
 * Migrações de esquema que rodam UMA vez por instalação (e não a cada requisição).
 *
 * Antes, cada requisição executava dezenas de ALTER TABLE / CREATE INDEX contra o banco
 * remoto, o que deixava tudo lento. Agora o resultado fica registrado em
 * app_schema_migrations; para refazer uma migração, troque o sufixo da chave (ex.: _v1 -> _v2).
 */

function schemaOnce(PDO $pdo, string $chave, callable $migrar): void
{
    static $aplicadas = null;

    if ($aplicadas === null) {
        try {
            $aplicadas = array_flip($pdo->query('SELECT chave FROM app_schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS app_schema_migrations (
                    chave TEXT PRIMARY KEY,
                    aplicado_em TIMESTAMPTZ DEFAULT NOW()
                )'
            );
            $aplicadas = [];
        }
    }

    if (isset($aplicadas[$chave])) {
        return;
    }

    $migrar();

    $pdo->prepare('INSERT INTO app_schema_migrations (chave) VALUES (:c) ON CONFLICT (chave) DO NOTHING')
        ->execute([':c' => $chave]);
    $aplicadas[$chave] = true;
}
