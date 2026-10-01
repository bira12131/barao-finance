<?php
/**
 * Guard de isolamento da integração Pierre por usuário.
 *
 * Em instalações com API key única, os dados externos são globais.
 * Para evitar vazamento entre contas locais, vinculamos a integração
 * a um único usuário (owner) e bloqueamos acesso/sync para os demais.
 */

require_once __DIR__ . '/../config/database.php';

function pierreEnsureOwnerTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pierre_integracao_owner (
            singleton_id SMALLINT PRIMARY KEY CHECK (singleton_id = 1),
            owner_user_id UUID NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
            criado_em TIMESTAMPTZ DEFAULT NOW()
        )"
    );
}

function pierreResolveOwnerId(PDO $pdo, string $currentUserId): string
{
    pierreEnsureOwnerTable($pdo);

    $stmt = $pdo->query('SELECT owner_user_id FROM pierre_integracao_owner WHERE singleton_id = 1 LIMIT 1');
    $owner = $stmt ? $stmt->fetchColumn() : false;
    if (is_string($owner) && $owner !== '') {
        return $owner;
    }

    // Se já existe histórico de sync, preserva o primeiro usuário que sincronizou.
    $legacyOwner = null;
    try {
        $legacyStmt = $pdo->query(
            "SELECT user_id
             FROM pierre_sincronizacoes
             WHERE user_id IS NOT NULL
             ORDER BY sincronizado_em ASC
             LIMIT 1"
        );
        $legacyOwner = $legacyStmt ? $legacyStmt->fetchColumn() : null;
    } catch (\Throwable $e) {
        $legacyOwner = null;
    }

    $ownerToUse = (is_string($legacyOwner) && $legacyOwner !== '') ? $legacyOwner : $currentUserId;

    $ins = $pdo->prepare(
        'INSERT INTO pierre_integracao_owner (singleton_id, owner_user_id)
         VALUES (1, :owner_user_id)
         ON CONFLICT (singleton_id) DO NOTHING'
    );
    $ins->execute([':owner_user_id' => $ownerToUse]);

    $stmt2 = $pdo->query('SELECT owner_user_id FROM pierre_integracao_owner WHERE singleton_id = 1 LIMIT 1');
    $owner2 = $stmt2 ? $stmt2->fetchColumn() : false;

    return (is_string($owner2) && $owner2 !== '') ? $owner2 : $ownerToUse;
}

function pierreGuardAccess(string $currentUserId): array
{
    $pdo = getConnection();
    $ownerId = pierreResolveOwnerId($pdo, $currentUserId);

    return [
        'allowed' => strcasecmp($ownerId, $currentUserId) === 0,
        'owner_user_id' => $ownerId,
    ];
}
