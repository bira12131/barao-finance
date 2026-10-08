<?php
/**
 * Configuração do assistente financeiro (IA).
 *
 * 'api_key': chave da API da Anthropic (console.anthropic.com), lida da variável ANTHROPIC_API_KEY.
 *            Vazia = assistente desligado.
 * 'modelo': modelo usado nas respostas.
 * 'max_mensagens_dia': limite de perguntas por usuário a cada 24h (controla o custo).
 */
return [
    'api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
    'modelo' => 'claude-sonnet-5-5',
    'max_mensagens_dia' => 60,
];
