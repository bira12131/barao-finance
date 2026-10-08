<?php
/**
 * REPOSITORY — CofreRepository
 *
 * Cofre de senhas. O servidor só guarda TEXTO CIFRADO: a criptografia acontece no navegador
 * (js/cofre-crypto.js), com chave derivada da senha mestra, que nunca chega até aqui.
 * Mesmo com acesso ao banco, ninguém lê as senhas sem a senha mestra ou a chave de recuperação.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/schema.php';

class CofreRepository
{
    public const MAX_ITENS = 3000;
    public const MAX_BLOB  = 70000;      // caracteres (base64) por item

    /** @var PDO */
    private $pdo;
    private string $userId;

    /** @param PDO|null $pdo */
    public function __construct(string $userId, $pdo = null)
    {
        $this->userId = trim($userId);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $this->userId)) {
            throw new InvalidArgumentException('userId inválido para CofreRepository.');
        }
        $this->pdo = $pdo ?? getConnection();
        schemaOnce($this->pdo, 'cofre_v1', fn() => $this->criarTabelas());
    }

    private function criarTabelas(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS cofre_config (
                user_id       UUID         PRIMARY KEY,
                versao        INT          NOT NULL DEFAULT 1,
                iter_mestra   INT          NOT NULL,
                salt_mestra   TEXT         NOT NULL,
                dek_mestra    TEXT         NOT NULL,
                iter_recup    INT          NOT NULL,
                salt_recup    TEXT         NOT NULL,
                dek_recup     TEXT         NOT NULL,
                criado_em     TIMESTAMPTZ  DEFAULT NOW(),
                atualizado_em TIMESTAMPTZ  DEFAULT NOW()
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS cofre_itens (
                id            UUID         PRIMARY KEY,
                user_id       UUID         NOT NULL,
                iv            TEXT         NOT NULL,
                ct            TEXT         NOT NULL,
                criado_em     TIMESTAMPTZ  DEFAULT NOW(),
                atualizado_em TIMESTAMPTZ  DEFAULT NOW()
            )"
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_cofre_itens_user ON cofre_itens (user_id)');
    }

    /* ════════════════════════════════════════════
       Validação (só formato: não dá para "validar" o que está cifrado)
    ════════════════════════════════════════════ */

    private static function b64(string $s, int $max): bool
    {
        return $s !== '' && strlen($s) <= $max && (bool)preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $s);
    }

    private static function idValido(string $id): bool
    {
        return (bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $id);
    }

    /** @return array{iv:string, ct:string}|null */
    private static function blobValido($blob, int $maxCt = self::MAX_BLOB): ?array
    {
        if (!is_array($blob)) return null;
        $iv = (string)($blob['iv'] ?? '');
        $ct = (string)($blob['ct'] ?? '');
        return (self::b64($iv, 32) && self::b64($ct, $maxCt)) ? ['iv' => $iv, 'ct' => $ct] : null;
    }

    /** Valida uma configuração completa (criar) ou parcial (trocar mestra / recuperação). */
    private static function validarConfig(array $c, array $campos): ?array
    {
        $out = [];
        foreach ($campos as $grupo) {
            $iter = (int)($c["iter_$grupo"] ?? 0);
            $salt = (string)($c["salt_$grupo"] ?? '');
            $dek  = self::blobValido($c["dek_$grupo"] ?? null, 200);
            if ($iter < 1000 || $iter > 5000000 || !self::b64($salt, 64) || $dek === null) return null;
            $out["iter_$grupo"] = $iter;
            $out["salt_$grupo"] = $salt;
            $out["dek_$grupo"]  = json_encode($dek);
        }
        return $out;
    }

    /* ════════════════════════════════════════════
       Configuração
    ════════════════════════════════════════════ */

    public function config(): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT versao, iter_mestra, salt_mestra, dek_mestra, iter_recup, salt_recup, dek_recup FROM cofre_config WHERE user_id = :u'
        );
        $stmt->execute([':u' => $this->userId]);
        $r = $stmt->fetch();
        if (!$r) return null;
        return [
            'versao'      => (int)$r['versao'],
            'iter_mestra' => (int)$r['iter_mestra'], 'salt_mestra' => $r['salt_mestra'], 'dek_mestra' => json_decode($r['dek_mestra'], true),
            'iter_recup'  => (int)$r['iter_recup'],  'salt_recup'  => $r['salt_recup'],  'dek_recup'  => json_decode($r['dek_recup'], true),
        ];
    }

    /** Cria o cofre. Falso se já existe ou se a configuração é inválida. */
    public function criar(array $config): bool
    {
        $v = self::validarConfig($config, ['mestra', 'recup']);
        if ($v === null || $this->config() !== null) return false;
        $stmt = $this->pdo->prepare(
            'INSERT INTO cofre_config (user_id, versao, iter_mestra, salt_mestra, dek_mestra, iter_recup, salt_recup, dek_recup)
             VALUES (:u, 1, :im, :sm, :dm, :ir, :sr, :dr)'
        );
        return $stmt->execute([
            ':u' => $this->userId, ':im' => $v['iter_mestra'], ':sm' => $v['salt_mestra'], ':dm' => $v['dek_mestra'],
            ':ir' => $v['iter_recup'], ':sr' => $v['salt_recup'], ':dr' => $v['dek_recup'],
        ]);
    }

    /** Troca só a parte da senha mestra (a chave de dados é a mesma: os itens não precisam ser regravados). */
    public function trocarMestra(array $c): bool
    {
        $v = self::validarConfig($c, ['mestra']);
        if ($v === null) return false;
        $stmt = $this->pdo->prepare(
            'UPDATE cofre_config SET iter_mestra = :i, salt_mestra = :s, dek_mestra = :d, atualizado_em = NOW() WHERE user_id = :u'
        );
        $stmt->execute([':i' => $v['iter_mestra'], ':s' => $v['salt_mestra'], ':d' => $v['dek_mestra'], ':u' => $this->userId]);
        return $stmt->rowCount() > 0;
    }

    public function trocarRecuperacao(array $c): bool
    {
        $v = self::validarConfig($c, ['recup']);
        if ($v === null) return false;
        $stmt = $this->pdo->prepare(
            'UPDATE cofre_config SET iter_recup = :i, salt_recup = :s, dek_recup = :d, atualizado_em = NOW() WHERE user_id = :u'
        );
        $stmt->execute([':i' => $v['iter_recup'], ':s' => $v['salt_recup'], ':d' => $v['dek_recup'], ':u' => $this->userId]);
        return $stmt->rowCount() > 0;
    }

    /** Apaga o cofre inteiro (configuração e itens). Irreversível. */
    public function apagarTudo(): void
    {
        $this->pdo->prepare('DELETE FROM cofre_itens WHERE user_id = :u')->execute([':u' => $this->userId]);
        $this->pdo->prepare('DELETE FROM cofre_config WHERE user_id = :u')->execute([':u' => $this->userId]);
    }

    /* ════════════════════════════════════════════
       Itens (sempre cifrados)
    ════════════════════════════════════════════ */

    public function itens(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, iv, ct, atualizado_em::text AS atualizado_em FROM cofre_itens WHERE user_id = :u ORDER BY criado_em ASC'
        );
        $stmt->execute([':u' => $this->userId]);
        return $stmt->fetchAll();
    }

    public function totalItens(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cofre_itens WHERE user_id = :u');
        $stmt->execute([':u' => $this->userId]);
        return (int)$stmt->fetchColumn();
    }

    /** Cria ou atualiza um item cifrado. Falso se inválido ou se o id pertence a outro usuário. */
    public function salvarItem(string $id, $blob): bool
    {
        $b = self::blobValido($blob);
        if ($b === null || !self::idValido($id)) return false;
        $id = strtolower($id);

        $dono = $this->pdo->prepare('SELECT user_id FROM cofre_itens WHERE id = :id');
        $dono->execute([':id' => $id]);
        $d = $dono->fetchColumn();
        if ($d !== false && strtolower((string)$d) !== strtolower($this->userId)) return false;
        if ($d === false && $this->totalItens() >= self::MAX_ITENS) return false;

        $stmt = $this->pdo->prepare(
            'INSERT INTO cofre_itens (id, user_id, iv, ct) VALUES (:id, :u, :iv, :ct)
             ON CONFLICT (id) DO UPDATE SET iv = EXCLUDED.iv, ct = EXCLUDED.ct, atualizado_em = NOW()
             WHERE cofre_itens.user_id = EXCLUDED.user_id'
        );
        $stmt->execute([':id' => $id, ':u' => $this->userId, ':iv' => $b['iv'], ':ct' => $b['ct']]);
        return true;
    }

    public function excluirItem(string $id): bool
    {
        if (!self::idValido($id)) return false;
        $stmt = $this->pdo->prepare('DELETE FROM cofre_itens WHERE id = :id AND user_id = :u');
        $stmt->execute([':id' => strtolower($id), ':u' => $this->userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Importa vários itens (já cifrados) de uma vez, em transação: ou entra tudo ou nada.
     * @return int quantos foram gravados
     */
    public function importar(array $itens): int
    {
        if (count($itens) > 600) throw new InvalidArgumentException('Máximo de 600 itens por vez.');
        if ($this->totalItens() + count($itens) > self::MAX_ITENS) throw new InvalidArgumentException('Limite de ' . self::MAX_ITENS . ' itens no cofre.');
        $this->pdo->beginTransaction();
        try {
            $n = 0;
            foreach ($itens as $it) {
                if (!$this->salvarItem((string)($it['id'] ?? ''), $it)) throw new InvalidArgumentException('Item inválido na importação.');
                $n++;
            }
            $this->pdo->commit();
            return $n;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
