<?php
/**
 * REPOSITORY — SnippetsRepository
 *
 * Banco de códigos e consultas: cada item é um texto com nome, linguagem e tags.
 * Fica em texto simples (para poder buscar). Para segredos, use o cofre de senhas.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/schema.php';

class SnippetsRepository
{
    public const LINGUAGENS = ['sql', 'vbnet', 'csharp', 'javascript', 'php', 'html', 'css', 'python', 'bash', 'json', 'xml', 'markdown', 'texto'];
    public const MAX_CONTEUDO = 500000;     // ~500 KB por item
    public const MAX_ITENS = 5000;

    /** @var PDO */
    private $pdo;
    private string $userId;

    /** @param PDO|null $pdo */
    public function __construct(string $userId, $pdo = null)
    {
        $this->userId = trim($userId);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $this->userId)) {
            throw new InvalidArgumentException('userId inválido para SnippetsRepository.');
        }
        $this->pdo = $pdo ?? getConnection();
        schemaOnce($this->pdo, 'snippets_v1', function () {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS snippets (
                    id            UUID         DEFAULT gen_random_uuid() PRIMARY KEY,
                    user_id       UUID         NOT NULL,
                    nome          TEXT         NOT NULL,
                    linguagem     VARCHAR(20)  NOT NULL DEFAULT 'texto',
                    tags          TEXT         NOT NULL DEFAULT '',
                    conteudo      TEXT         NOT NULL DEFAULT '',
                    favorito      BOOLEAN      NOT NULL DEFAULT FALSE,
                    criado_em     TIMESTAMPTZ  DEFAULT NOW(),
                    atualizado_em TIMESTAMPTZ  DEFAULT NOW()
                )"
            );
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_snippets_user ON snippets (user_id, atualizado_em DESC)');
        });
    }

    private static function idValido(string $id): bool
    {
        return (bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $id);
    }

    /** Normaliza a entrada; null se inválida. */
    public function normalizar(array $in): ?array
    {
        $nome = trim((string)($in['nome'] ?? ''));
        $conteudo = (string)($in['conteudo'] ?? '');
        $ling = strtolower(trim((string)($in['linguagem'] ?? 'texto')));
        if ($nome === '' || mb_strlen($nome) > 200 || strlen($conteudo) > self::MAX_CONTEUDO) return null;
        if (!in_array($ling, self::LINGUAGENS, true)) $ling = 'texto';

        // tags: lista separada por vírgula, sem repetição, minúsculas
        $tags = [];
        foreach (preg_split('/[,;]+/', (string)($in['tags'] ?? '')) ?: [] as $t) {
            $t = mb_strtolower(trim($t));
            if ($t !== '' && mb_strlen($t) <= 30 && !in_array($t, $tags, true)) $tags[] = $t;
        }
        return [
            'nome' => $nome, 'linguagem' => $ling, 'tags' => implode(',', array_slice($tags, 0, 12)),
            'conteudo' => str_replace(["\r\n", "\r"], "\n", $conteudo),
            'favorito' => !empty($in['favorito']),
        ];
    }

    /** Lista sem o conteúdo inteiro (só um trecho), com busca por nome, tag e conteúdo. */
    public function listar(?string $q = null, ?string $linguagem = null, bool $somenteFavoritos = false, ?string $tag = null): array
    {
        $where = ['user_id = :u'];
        $params = [':u' => $this->userId];
        $q = $q !== null ? trim($q) : '';
        if ($q !== '') {
            $where[] = '(nome ILIKE :q OR tags ILIKE :q OR conteudo ILIKE :q)';
            $params[':q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        }
        if ($linguagem !== null && in_array($linguagem, self::LINGUAGENS, true)) {
            $where[] = 'linguagem = :l';
            $params[':l'] = $linguagem;
        }
        if ($tag !== null && trim($tag) !== '') {
            $where[] = "(',' || tags || ',') LIKE :t";
            $params[':t'] = '%,' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($tag))) . ',%';
        }
        if ($somenteFavoritos) $where[] = 'favorito = TRUE';

        $stmt = $this->pdo->prepare(
            "SELECT id, nome, linguagem, tags, CASE WHEN favorito THEN 1 ELSE 0 END AS favorito,
                    LEFT(conteudo, 160) AS previa, LENGTH(conteudo) AS tamanho, atualizado_em::text AS atualizado_em
             FROM snippets WHERE " . implode(' AND ', $where) . "
             ORDER BY favorito DESC, atualizado_em DESC LIMIT 500"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) { $r['favorito'] = (int)$r['favorito'] === 1; $r['tamanho'] = (int)$r['tamanho']; }
        unset($r);
        return $rows;
    }

    /** Todas as tags em uso, com contagem. */
    public function tags(): array
    {
        $stmt = $this->pdo->prepare("SELECT tags FROM snippets WHERE user_id = :u AND tags <> ''");
        $stmt->execute([':u' => $this->userId]);
        $cont = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $linha) foreach (explode(',', $linha) as $t) if ($t !== '') $cont[$t] = ($cont[$t] ?? 0) + 1;
        arsort($cont);
        $out = [];
        foreach ($cont as $t => $n) $out[] = ['tag' => $t, 'total' => $n];
        return $out;
    }

    public function obter(string $id): ?array
    {
        if (!self::idValido($id)) return null;
        $stmt = $this->pdo->prepare(
            "SELECT id, nome, linguagem, tags, conteudo, CASE WHEN favorito THEN 1 ELSE 0 END AS favorito,
                    atualizado_em::text AS atualizado_em, criado_em::text AS criado_em
             FROM snippets WHERE id = :id AND user_id = :u"
        );
        $stmt->execute([':id' => strtolower($id), ':u' => $this->userId]);
        $r = $stmt->fetch();
        if (!$r) return null;
        $r['favorito'] = (int)$r['favorito'] === 1;
        return $r;
    }

    public function criar(array $d): ?string
    {
        $cnt = $this->pdo->prepare('SELECT COUNT(*) FROM snippets WHERE user_id = :u');
        $cnt->execute([':u' => $this->userId]);
        if ((int)$cnt->fetchColumn() >= self::MAX_ITENS) return null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO snippets (user_id, nome, linguagem, tags, conteudo, favorito)
             VALUES (:u, :n, :l, :t, :c, :f) RETURNING id'
        );
        $stmt->bindValue(':u', $this->userId);
        $stmt->bindValue(':n', $d['nome']);
        $stmt->bindValue(':l', $d['linguagem']);
        $stmt->bindValue(':t', $d['tags']);
        $stmt->bindValue(':c', $d['conteudo']);
        $stmt->bindValue(':f', $d['favorito'], PDO::PARAM_BOOL);   // PDO manda false como '' e o PostgreSQL recusa
        $stmt->execute();
        return (string)$stmt->fetchColumn();
    }

    public function atualizar(string $id, array $d): bool
    {
        if (!self::idValido($id)) return false;
        $stmt = $this->pdo->prepare(
            'UPDATE snippets SET nome = :n, linguagem = :l, tags = :t, conteudo = :c, favorito = :f, atualizado_em = NOW()
             WHERE id = :id AND user_id = :u'
        );
        $stmt->bindValue(':n', $d['nome']);
        $stmt->bindValue(':l', $d['linguagem']);
        $stmt->bindValue(':t', $d['tags']);
        $stmt->bindValue(':c', $d['conteudo']);
        $stmt->bindValue(':f', $d['favorito'], PDO::PARAM_BOOL);
        $stmt->bindValue(':id', strtolower($id));
        $stmt->bindValue(':u', $this->userId);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function favoritar(string $id, bool $favorito): bool
    {
        if (!self::idValido($id)) return false;
        $stmt = $this->pdo->prepare('UPDATE snippets SET favorito = :f WHERE id = :id AND user_id = :u');
        $stmt->bindValue(':f', $favorito, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', strtolower($id));
        $stmt->bindValue(':u', $this->userId);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function excluir(string $id): bool
    {
        if (!self::idValido($id)) return false;
        $stmt = $this->pdo->prepare('DELETE FROM snippets WHERE id = :id AND user_id = :u');
        $stmt->execute([':id' => strtolower($id), ':u' => $this->userId]);
        return $stmt->rowCount() > 0;
    }
}
