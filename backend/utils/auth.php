<?php
/**
 * Helpers de autenticação por sessão PHP.
 */

require_once __DIR__ . '/response.php';

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 60 * 60 * 12; // 12 horas

        ini_set('session.gc_maxlifetime', (string)$lifetime);
        ini_set('session.cookie_lifetime', (string)$lifetime);

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('baraofinance_session_id');
        session_start();

        // Expiração por inatividade com renovação a cada requisição autenticada.
        $agora = time();
        $ultimoAcesso = $_SESSION['last_activity_ts'] ?? $agora;

        if (($agora - (int)$ultimoAcesso) > $lifetime) {
            $_SESSION = [];
            session_destroy();
            session_name('baraofinance_session_id');
            session_start();
        }

        $_SESSION['last_activity_ts'] = $agora;
    }
}

function requireAuthenticatedUserId(): string
{
    startAppSession();

    $userId = $_SESSION['user_id'] ?? null;
    if (!is_string($userId) || $userId === '') {
        jsonResponse(false, 'Sessão inválida ou expirada. Faça login novamente.', [], 401);
    }

    if (!preg_match('/^[a-f0-9-]{36}$/i', $userId)) {
        jsonResponse(false, 'Sessão inválida.', [], 401);
    }

    // Libera o lock da sessão: sem isso, requisições simultâneas ficam em fila até a anterior terminar.
    session_write_close();

    return $userId;
}

function logoutSession(): void
{
    startAppSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }

    session_destroy();
}
