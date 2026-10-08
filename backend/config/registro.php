<?php
/**
 * Controle de cadastro de novos usuários.
 *
 * CODIGO_CONVITE vazio = cadastro FECHADO (ninguém consegue criar conta).
 * Para convidar alguém, defina um código longo e aleatório (ex.: openssl rand -hex 16),
 * passe só para quem deve entrar e esvazie de novo depois.
 */
return [
    'codigo_convite' => getenv('CODIGO_CONVITE') ?: '',
];
