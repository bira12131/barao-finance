<?php
/**
 * Credenciais do iCloud para sincronizar a Agenda com o Calendário do iPhone.
 *
 * Os valores vêm de variáveis de ambiente (veja .env.example); nada fica no código.
 *
 * Como preencher:
 *  1. Acesse https://account.apple.com  ->  Login e Segurança  ->  Senhas de app (App-Specific Passwords).
 *  2. Gere uma senha com um nome como "Barao Finance" (formato xxxx-xxxx-xxxx-xxxx).
 *  3. Informe em ICLOUD_APPLE_ID e ICLOUD_APP_PASSWORD. Use a senha DE APP, nunca a senha do Apple ID.
 *  4. Na Agenda do app, abra "Sincronizar com iPhone" e toque em "Testar conexão".
 */

define('ICLOUD_APPLE_ID', getenv('ICLOUD_APPLE_ID') ?: '');
define('ICLOUD_APP_PASSWORD', getenv('ICLOUD_APP_PASSWORD') ?: '');
