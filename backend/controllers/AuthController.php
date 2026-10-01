<?php
/**
 * CONTROLLER — AuthController
 *
 * Orquestra o fluxo de autenticação:
 *  1. Valida a entrada (regras de negócio)
 *  2. Usa o UserModel para acessar os dados
 *  3. Usa jsonResponse() para devolver a resposta HTTP
 *
 * Não conhece SQL — delega tudo ao Model.
 */

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';

class AuthController
{
    private UserModel $model;

    public function __construct()
    {
        $this->model = new UserModel();
    }

    /* ────────────────────────────────────────────
       POST /backend/api/register.php
    ──────────────────────────────────────────── */
    public function register(array $body): void
    {
        $name     = trim($body['name']     ?? '');
        $email    = trim(strtolower($body['email']    ?? ''));
        $password = $body['password'] ?? '';

        // ── Validação ───────────────────────────
        $errors = [];

        if (strlen($name) < 2 || strlen($name) > 100) {
            $errors['name'] = 'Nome deve ter entre 2 e 100 caracteres.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail válido.';
        }

        if (strlen($password) < 6) {
            $errors['password'] = 'A senha deve ter ao menos 6 caracteres.';
        }

        if (!empty($errors)) {
            jsonResponse(false, 'Dados inválidos.', ['errors' => $errors], 422);
        }

        // ── Regra de negócio: e-mail único ──────
        if ($this->model->findByEmail($email) !== null) {
            jsonResponse(false, 'E-mail já cadastrado.', [
                'errors' => ['email' => 'Este e-mail já está cadastrado.'],
            ], 409);
        }

        // ── Cria o usuário ───────────────────────
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $user = $this->model->create($name, $email, $hash);

        jsonResponse(true, 'Cadastro realizado com sucesso!', ['user' => $user], 201);
    }

    /* ────────────────────────────────────────────
       POST /backend/api/login.php
    ──────────────────────────────────────────── */
    public function login(array $body): void
    {
        $email    = trim(strtolower($body['email']    ?? ''));
        $password = $body['password'] ?? '';

        // ── Validação ───────────────────────────
        if (!$email || !$password) {
            jsonResponse(false, 'Preencha todos os campos.', [
                'errors' => ['general' => 'E-mail e senha são obrigatórios.'],
            ], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'E-mail inválido.', [
                'errors' => ['email' => 'Informe um e-mail válido.'],
            ], 422);
        }

        // ── Autenticação ─────────────────────────
        $user = $this->model->findByEmail($email);

        // Resposta genérica — não revela se o e-mail existe
        if ($user === null || !password_verify($password, $user['senha_hash'])) {
            jsonResponse(false, 'E-mail ou senha incorretos.', [
                'errors' => ['general' => 'E-mail ou senha incorretos.'],
            ], 401);
        }

        // Remove o hash antes de retornar ao frontend
        unset($user['senha_hash']);

        // Sessão server-side para identificar o usuário em todos os endpoints Pierre
        startAppSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['nome'];

        jsonResponse(true, 'Login realizado com sucesso!', ['user' => $user]);
    }
}
