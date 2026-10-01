<?php
/**
 * Conexão com o banco de dados PostgreSQL (KingHost)
 *
 * ATENÇÃO: Adicione este arquivo ao .gitignore.
 * Nunca suba credenciais reais para repositórios públicos.
 */

define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_PORT', '5432');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');   // KingHost usa o nome do banco como usuário
define('DB_PASS', getenv('DB_PASS') ?: '');

/**
 * Retorna uma instância PDO singleton.
 * Em caso de falha, envia resposta JSON 500 e encerra.
 */
function getConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            DB_HOST, DB_PORT, DB_NAME
        );

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro de conexão com o banco de dados.',
            ]);
            exit;
        }
    }

    return $pdo;
}
