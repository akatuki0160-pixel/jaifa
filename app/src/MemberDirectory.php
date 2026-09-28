<?php

declare(strict_types=1);

namespace Qryno;

use PDO;
use RuntimeException;

/**
 * 取込済み会員データ（SQLite）の検索
 *
 * 検索条件4項目の複合インデックスで引くため、4万件でも1ms未満で応答します。
 */
final class MemberDirectory
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $path)
    {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isReady(): bool
    {
        return is_file($this->path) && filesize($this->path) > 0;
    }

    /** 4項目（正規化済みのキー）が完全一致する会員の会員番号を探します */
    public function find(string $kyokai, string $company, string $sei, string $mei): SearchResult
    {
        $statement = $this->connect()->prepare(
            'SELECT DISTINCT member_no FROM members
              WHERE sei = ? AND mei = ? AND kyokai = ? AND company = ?
              LIMIT 2'
        );
        $statement->execute([$sei, $mei, $kyokai, $company]);
        $numbers = $statement->fetchAll(PDO::FETCH_COLUMN);

        return match (count($numbers)) {
            0 => SearchResult::notFound(),
            1 => SearchResult::found((string) $numbers[0]),
            default => SearchResult::multiple(),
        };
    }

    /** @return array<string, string> 取込日時・件数などの情報。未取込なら空配列 */
    public function meta(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        return $this->connect()->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function count(): int
    {
        return (int) ($this->meta()['row_count'] ?? 0);
    }

    public static function createSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE members (
                member_no TEXT NOT NULL,
                kyokai    TEXT NOT NULL,
                company   TEXT NOT NULL,
                sei       TEXT NOT NULL,
                mei       TEXT NOT NULL,
                row_no    INTEGER NOT NULL
            )'
        );
        $pdo->exec('CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
    }

    public static function createIndexes(PDO $pdo): void
    {
        $pdo->exec('CREATE INDEX members_lookup ON members (sei, mei, kyokai, company)');
        $pdo->exec('CREATE INDEX members_member_no ON members (member_no)');
    }

    private function connect(): PDO
    {
        if ($this->pdo === null) {
            if (!$this->isReady()) {
                throw new RuntimeException('会員データが取り込まれていません。');
            }
            $this->pdo = new PDO('sqlite:' . $this->path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->pdo->exec('PRAGMA query_only = ON');
        }

        return $this->pdo;
    }
}
