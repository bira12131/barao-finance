<?php
/**
 * Fachada da sincronização com o iCloud: credenciais (arquivo de config), trava contra execuções
 * simultâneas e estado exibido na tela.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/AgendaRepository.php';
require_once __DIR__ . '/IcloudSyncEngine.php';
require_once __DIR__ . '/IcloudPgStore.php';

class IcloudSyncService
{
    /** Endereço do servidor CalDAV; só muda em testes (padrão: iCloud). */
    public static ?string $urlBase = null;

    private string $userId;
    private PDO $pdo;
    private IcloudPgStore $store;

    public function __construct(string $userId)
    {
        $this->userId = $userId;
        new AgendaRepository($userId);          // garante as tabelas e colunas
        $this->pdo   = getConnection();
        $this->store = new IcloudPgStore($this->pdo, $userId);
    }

    /* ── credenciais (backend/config/icloud.php, fora do repositório) ── */

    private static function carregarConfig(): void
    {
        $arq = __DIR__ . '/../config/icloud.php';
        if (!defined('ICLOUD_APPLE_ID') && is_file($arq)) require_once $arq;
    }

    /** @return array{0:string,1:string}|null */
    public static function credenciais(): ?array
    {
        self::carregarConfig();
        if (!defined('ICLOUD_APPLE_ID') || !defined('ICLOUD_APP_PASSWORD')) return null;
        $id = trim((string)ICLOUD_APPLE_ID);
        $senha = trim((string)ICLOUD_APP_PASSWORD);
        return ($id !== '' && $senha !== '') ? [$id, $senha] : null;
    }

    public static function appleIdMascarado(): ?string
    {
        $c = self::credenciais();
        if ($c === null || strpos($c[0], '@') === false) return null;
        [$u, $d] = explode('@', $c[0], 2);
        return substr($u, 0, 1) . str_repeat('*', max(1, strlen($u) - 1)) . '@' . $d;
    }

    private function cliente(): IcloudCalDav
    {
        $c = self::credenciais();
        if ($c === null) {
            throw new RuntimeException('Credenciais do iCloud ausentes: preencha backend/config/icloud.php com o Apple ID e a senha específica de app.');
        }
        return self::$urlBase !== null ? new IcloudCalDav($c[0], $c[1], self::$urlBase) : new IcloudCalDav($c[0], $c[1]);
    }

    /* ── ações ── */

    /** Estado para a tela (sem segredos). */
    public function estado(): array
    {
        $e = $this->store->estado();
        $resultado = $e['ultimo_resultado'] ? json_decode($e['ultimo_resultado'], true) : null;
        $minutos = $e['ultima_sync'] ? (time() - strtotime($e['ultima_sync'])) / 60 : null;
        return [
            'configurado'       => self::credenciais() !== null,
            'apple_id'          => self::appleIdMascarado(),
            'habilitado'        => $e['habilitado'],
            'ultima_sync'       => $e['ultima_sync'],
            'ultimo_resultado'  => is_array($resultado) ? $resultado : null,
            'calendarios'       => array_values(array_filter($e['calendarios'], static fn($c) => empty($c['app']))),
            'deve_sincronizar'  => $e['habilitado'] && ($minutos === null || $minutos > 10),
        ];
    }

    /** Conecta, lista os calendários do iCloud e guarda a lista. Não escreve nada no iCloud. */
    public function testar(): array
    {
        $engine = new IcloudSyncEngine($this->cliente(), $this->store);
        $d = $engine->descobrir(false);
        return array_values(array_filter($d['calendarios'], static fn($c) => empty($c['app'])));
    }

    public function selecionar(array $hrefs): void
    {
        $e = $this->store->estado();
        $lista = [];
        foreach ($e['calendarios'] as $c) {
            if (empty($c['app'])) $c['selecionado'] = in_array($c['href'], $hrefs, true);
            $lista[] = $c;
        }
        $this->store->salvarEstado(['calendarios' => $lista]);
    }

    public function habilitar(bool $ligar): void
    {
        $this->store->salvarEstado(['habilitado' => $ligar]);
    }

    /** Executa a sincronização (uma por vez por usuário). */
    public function sincronizar(): array
    {
        $trava = $this->pdo->prepare('SELECT pg_try_advisory_lock(hashtext(:k))::int');
        $trava->execute([':k' => 'icloud:' . $this->userId]);
        if ((int)$trava->fetchColumn() !== 1) {
            throw new RuntimeException('Já existe uma sincronização em andamento. Aguarde alguns segundos.');
        }
        try {
            @set_time_limit(180);
            $engine = new IcloudSyncEngine($this->cliente(), $this->store);
            return $engine->sincronizar();
        } finally {
            $this->pdo->prepare('SELECT pg_advisory_unlock(hashtext(:k))')->execute([':k' => 'icloud:' . $this->userId]);
        }
    }
}
