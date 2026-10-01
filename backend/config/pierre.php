<?php
/**
 * Configuração da integração Pierre Finance
 *
 * ATENÇÃO: Este arquivo contém credenciais sensíveis.
 * Nunca commite no controle de versão (.gitignore).
 */

define('PIERRE_API_KEY', getenv('PIERRE_API_KEY') ?: '');
define('PIERRE_BASE_URL', 'https://www.pierre.finance/tools/api');
