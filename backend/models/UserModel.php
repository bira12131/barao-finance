<?php
/**
 * MODEL — UserModel
 *
 * Responsável exclusivamente pelo acesso aos dados
 * da tabela `users` no banco PostgreSQL.
 * Não conhece HTTP, não gera resposta — só faz queries.
 */

require_once __DIR__ . '/../config/database.php';

class UserModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getConnection();
    }

    /**
     * Busca um usuário pelo e-mail.
     * Retorna o array com os dados (incluindo password_hash) ou null.
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, nome, email, senha_hash
            FROM usuarios
            WHERE email = :email
            LIMIT 1
        ');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Insere um novo usuário e retorna os dados públicos (sem hash).
     */
    public function create(string $name, string $email, string $passwordHash): array
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO usuarios (nome, email, senha_hash)
            VALUES (:nome, :email, :hash)
            RETURNING id, nome, email, criado_em
        ');
        $stmt->execute([
            ':nome'  => trim($name),
            ':email' => strtolower(trim($email)),
            ':hash'  => $passwordHash,
        ]);
        return $stmt->fetch();
    }
}
