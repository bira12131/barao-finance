<?php
/**
 * Logos das empresas de assinatura.
 *
 * A API da Pierre não fornece logos de estabelecimentos. Reconhecemos a marca pelo nome e usamos o
 * ícone do site oficial (serviço de favicons do Google). A tela mostra uma letra colorida quando a marca
 * não é conhecida ou a imagem não carrega.
 */

function logoDaMarca(?string $texto): ?string
{
    if ($texto === null || trim($texto) === '') return null;

    static $mapa = [
        'netflix' => 'netflix.com', 'spotify' => 'spotify.com', 'disney' => 'disneyplus.com',
        'hbo' => 'max.com', '\bmax\b' => 'max.com', 'prime ?video|amazon ?prime' => 'primevideo.com',
        'amazon' => 'amazon.com.br', 'youtube' => 'youtube.com', 'icloud' => 'icloud.com',
        'apple' => 'apple.com', 'google' => 'google.com', 'microsoft|office ?365' => 'microsoft.com',
        'adobe' => 'adobe.com', 'canva' => 'canva.com', 'chatgpt|openai' => 'openai.com',
        'anthropic|claude' => 'claude.ai', 'github' => 'github.com', 'notion' => 'notion.so',
        'dropbox' => 'dropbox.com', 'linkedin' => 'linkedin.com', 'duolingo' => 'duolingo.com',
        'deezer' => 'deezer.com', 'globoplay' => 'globoplay.globo.com', 'paramount' => 'paramountplus.com',
        'crunchyroll' => 'crunchyroll.com', 'meli\+|mercado ?livre' => 'mercadolivre.com.br',
        'uber' => 'uber.com', 'smart ?fit' => 'smartfit.com.br', 'bluefit' => 'bluefit.com.br',
        'gympass|wellhub' => 'wellhub.com', '\bvivo\b' => 'vivo.com.br', '\bclaro\b' => 'claro.com.br',
        '\btim\b' => 'tim.com.br', '\boi\b' => 'oi.com.br', '\bsky\b' => 'sky.com.br',
        'tidal' => 'tidal.com', 'telecine' => 'telecine.com.br', 'premiere' => 'globo.com',
        'cursor' => 'cursor.com', 'figma' => 'figma.com', 'slack' => 'slack.com', 'zoom' => 'zoom.us',
        'midjourney' => 'midjourney.com', 'replit' => 'replit.com', 'vercel' => 'vercel.com',
        'hostinger' => 'hostinger.com.br', 'godaddy' => 'godaddy.com', 'locaweb' => 'locaweb.com.br',
        'cloudflare' => 'cloudflare.com', 'ifood' => 'ifood.com.br', 'tiktok' => 'tiktok.com',
        'kinghost' => 'king.host', 'itau' => 'itau.com.br', 'nubank' => 'nubank.com.br',
    ];

    $t = strtr(mb_strtolower($texto), ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
    foreach ($mapa as $regex => $dominio) {
        if (preg_match('/' . $regex . '/u', $t)) {
            return 'https://www.google.com/s2/favicons?sz=128&domain=' . $dominio;
        }
    }
    return null;
}
