<?php
/**
 * SERVICE — WhatsappService
 *
 * Robô do WhatsApp via Evolution API: recebe o webhook, valida que a mensagem vem do número autorizado,
 * transcreve áudio (OpenAI), passa o texto ao assistente financeiro e responde pelo mesmo chat.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../repositories/AssistenteRepository.php';
require_once __DIR__ . '/AssistenteService.php';

class WhatsappService
{
    private const PREFIXO_BOT = '🤖 ';

    private array $cfg;
    private PDO $pdo;

    public function __construct()
    {
        $c = @include __DIR__ . '/../config/whatsapp.php';
        $this->cfg = is_array($c) ? $c : [];
        $this->pdo = getConnection();
        schemaOnce($this->pdo, 'whatsapp_v1', fn() => $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS whatsapp_processadas (
                msg_id    TEXT PRIMARY KEY,
                criado_em TIMESTAMPTZ DEFAULT NOW()
            )'
        ));
    }

    public function segredoValido(string $recebido): bool
    {
        $segredo = (string)($this->cfg['segredo_webhook'] ?? '');
        return $segredo !== '' && hash_equals($segredo, $recebido);
    }

    /** Só dígitos; celular BR sem o 9º dígito (55 + DDD + 8) vira o formato com 9, pois o WhatsApp usa os dois. */
    private function soDigitos(?string $v): string
    {
        $n = preg_replace('/\D+/', '', explode('@', (string)$v)[0]) ?? '';
        if (strlen($n) === 12 && str_starts_with($n, '55') && $n[4] >= '6') {
            $n = substr($n, 0, 4) . '9' . substr($n, 4);
        }
        return $n;
    }

    /** Registra o id; false se já tinha sido registrado (mensagem repetida ou resposta do próprio robô). */
    private function marcar(string $id): bool
    {
        if ($id === '') return false;
        $stmt = $this->pdo->prepare('INSERT INTO whatsapp_processadas (msg_id) VALUES (:i) ON CONFLICT (msg_id) DO NOTHING');
        $stmt->execute([':i' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Trata um evento do webhook. Não lança exceção: erros viram log e, quando possível, aviso ao usuário. */
    public function tratar(array $evt): void
    {
        $evento = strtolower((string)($evt['event'] ?? ''));
        if ($evento !== '' && !in_array($evento, ['messages.upsert', 'messages_upsert'], true)) return;

        $instancia = (string)($this->cfg['instancia'] ?? '');
        if ($instancia !== '' && (string)($evt['instance'] ?? '') !== $instancia) return;

        $d = $evt['data'] ?? null;
        if (!is_array($d)) return;
        // Alguns formatos mandam uma lista de mensagens.
        if (isset($d['messages'][0]) && is_array($d['messages'][0])) $d = $d['messages'][0];

        $key = is_array($d['key'] ?? null) ? $d['key'] : [];
        $msgId = (string)($key['id'] ?? '');
        $autorizado = $this->soDigitos((string)($this->cfg['numero_autorizado'] ?? ''));
        if ($autorizado === '' || $msgId === '') return;

        // O chat precisa ser com o número autorizado (conversa dele com o robô, ou o "Você" dele).
        $jids = [$key['remoteJid'] ?? null, $key['remoteJidAlt'] ?? null, $d['senderPn'] ?? null];
        $remoto = array_map(fn($j) => $this->soDigitos(is_string($j) ? $j : null), $jids);
        if (!in_array($autorizado, $remoto, true)) return;
        if (str_contains((string)($key['remoteJid'] ?? ''), '@g.us')) return;   // nunca em grupos

        // Anti-eco: respostas do próprio robô voltam pelo webhook.
        if (!$this->marcar($msgId)) return;

        $msg = is_array($d['message'] ?? null) ? $d['message'] : [];
        $texto = trim((string)($msg['conversation'] ?? ($msg['extendedTextMessage']['text'] ?? '')));
        $ehAudio = $texto === '' && isset($msg['audioMessage']);

        if (($texto === '' && !$ehAudio) || str_starts_with($texto, trim(self::PREFIXO_BOT))) return;

        // Confirmação imediata de que a mensagem chegou: 👀 na mensagem e "digitando…" enquanto processa.
        $this->reagir($key, '👀');
        $this->digitando($autorizado);

        if ($ehAudio) {
            try {
                $texto = $this->transcrever($msgId, $msg);
            } catch (\Throwable $e) {
                $this->reagir($key, '❌');
                $this->enviar($autorizado, 'Não consegui entender o áudio (' . $e->getMessage() . '). Pode mandar em texto?');
                return;
            }
            $this->digitando($autorizado);
        }

        if (mb_strlen($texto) > 2000) $texto = mb_substr($texto, 0, 2000);

        try {
            $user = (new UserModel())->findByEmail((string)($this->cfg['user_email'] ?? ''));
            if ($user === null) { $this->reagir($key, '❌'); $this->enviar($autorizado, 'Usuário do app não encontrado. Confira user_email na configuração.'); return; }

            $repo = new AssistenteRepository($user['id']);
            $servico = new AssistenteService($repo);
            if (!$servico->habilitado()) { $this->reagir($key, '❌'); $this->enviar($autorizado, 'A IA ainda não foi ativada no servidor.'); return; }
            if ($repo->mensagensUltimas24h() >= $servico->limiteDiario()) { $this->reagir($key, '❌'); $this->enviar($autorizado, 'Limite diário de mensagens da IA atingido.'); return; }

            $r = $servico->responder($texto, 'whatsapp');
            $this->enviar($autorizado, $r['resposta']);
            $this->reagir($key, '✅');
        } catch (RuntimeException $e) {
            // Mensagens deste tipo já vêm em linguagem amigável (IA fora do ar, chave inválida, etc.).
            $this->reagir($key, '❌');
            $this->enviar($autorizado, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[whatsapp] ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            $this->reagir($key, '❌');
            $this->enviar($autorizado, 'Tive um problema para processar agora (' . mb_substr($e->getMessage(), 0, 140) . ').');
        }
    }

    /* ════════════════════════════════════════════
       Evolution API
    ════════════════════════════════════════════ */

    private function evolution(string $metodo, string $caminho, ?array $corpo = null, int $timeout = 30): array
    {
        $ch = curl_init(rtrim((string)$this->cfg['evolution_url'], '/') . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'apikey: ' . $this->cfg['evolution_apikey']],
        ]);
        if ($corpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = is_string($raw) ? json_decode($raw, true) : null;
        if ($http < 200 || $http >= 300 || !is_array($json)) {
            throw new RuntimeException('Evolution respondeu ' . $http);
        }
        return $json;
    }

    /** Reação (emoji) na mensagem recebida; "best effort": falha não atrapalha o fluxo. */
    private function reagir(array $key, string $emoji): void
    {
        try {
            $this->evolution('POST', '/message/sendReaction/' . rawurlencode((string)$this->cfg['instancia']), [
                'key' => [
                    'remoteJid' => $key['remoteJid'] ?? '',
                    'fromMe' => (bool)($key['fromMe'] ?? false),
                    'id' => $key['id'] ?? '',
                ],
                'reaction' => $emoji,
            ], 8);
        } catch (\Throwable $e) {
            error_log('[whatsapp] reação falhou: ' . $e->getMessage());
        }
    }

    /** Mostra "digitando…" por até 25s (some sozinho quando a resposta é enviada). */
    private function digitando(string $numero): void
    {
        try {
            $this->evolution('POST', '/chat/sendPresence/' . rawurlencode((string)$this->cfg['instancia']), [
                'number' => $numero,
                'delay' => 25000,
                'presence' => 'composing',
            ], 8);
        } catch (\Throwable $e) {
            error_log('[whatsapp] presença falhou: ' . $e->getMessage());
        }
    }

    private function enviar(string $numero, string $texto): void
    {
        try {
            $r = $this->evolution('POST', '/message/sendText/' . rawurlencode((string)$this->cfg['instancia']), [
                'number' => $numero,
                'text' => self::PREFIXO_BOT . $texto,
            ]);
            // Marca o id da própria resposta para não reprocessar o eco.
            $this->marcar((string)($r['key']['id'] ?? ''));
        } catch (\Throwable $e) {
            error_log('[whatsapp] falha ao enviar: ' . $e->getMessage());
        }
    }

    /* ════════════════════════════════════════════
       Áudio -> texto (OpenAI)
    ════════════════════════════════════════════ */

    private function transcrever(string $msgId, array $msg): string
    {
        $chave = trim((string)($this->cfg['openai_api_key'] ?? ''));
        if ($chave === '') throw new RuntimeException('transcrição não configurada');

        $b64 = (string)($msg['base64'] ?? '');
        if ($b64 === '') {
            $r = $this->evolution('POST', '/chat/getBase64FromMediaMessage/' . rawurlencode((string)$this->cfg['instancia']), [
                'message' => ['key' => ['id' => $msgId]],
                'convertToMp4' => false,
            ]);
            $b64 = (string)($r['base64'] ?? '');
        }
        $bin = base64_decode($b64, true);
        if ($bin === false || $bin === '' || strlen($bin) > 20 * 1024 * 1024) throw new RuntimeException('áudio indisponível');

        $tmp = tempnam(sys_get_temp_dir(), 'wpp') . '.ogg';
        file_put_contents($tmp, $bin);

        try {
            $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $chave],
                CURLOPT_POSTFIELDS => [
                    'file' => new CURLFile($tmp, 'audio/ogg', 'audio.ogg'),
                    'model' => (string)($this->cfg['openai_modelo_audio'] ?? 'gpt-4o-mini-transcribe'),
                    'language' => 'pt',
                ],
            ]);
            $raw = curl_exec($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } finally {
            @unlink($tmp);
        }

        $json = is_string($raw) ? json_decode($raw, true) : null;
        $texto = is_array($json) ? trim((string)($json['text'] ?? '')) : '';
        if ($http !== 200 || $texto === '') throw new RuntimeException('transcrição falhou (' . $http . ')');
        return $texto;
    }
}
