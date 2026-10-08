<?php
/**
 * Robô do WhatsApp (Evolution API) — configuração.
 *
 * Só o número 'numero_autorizado' é atendido; qualquer outro é ignorado em silêncio.
 * As ações valem para a conta do usuário com o e-mail 'user_email'.
 * Todos os valores vêm de variáveis de ambiente (veja .env.example).
 */
return [
    'evolution_url'       => getenv('EVOLUTION_URL') ?: '',
    'evolution_apikey'    => getenv('EVOLUTION_APIKEY') ?: '',
    'instancia'           => getenv('EVOLUTION_INSTANCIA') ?: '',
    'numero_autorizado'   => getenv('WHATSAPP_NUMERO_AUTORIZADO') ?: '',
    'user_email'          => getenv('WHATSAPP_USER_EMAIL') ?: '',
    'segredo_webhook'     => getenv('WHATSAPP_SEGREDO_WEBHOOK') ?: '',
    'openai_api_key'      => getenv('OPENAI_API_KEY') ?: '',   // transcrição de áudio
    'openai_modelo_audio' => 'gpt-4o-mini-transcribe',
];
