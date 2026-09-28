<?php

declare(strict_types=1);

namespace Qryno;

use Closure;
use PDO;
use Throwable;

/**
 * 接続元ごとの試行回数制限（総当たり対策）
 *
 * IPアドレスはそのまま保存せず、秘密鍵付きハッシュにして保存します。
 * 記録に失敗した場合は利用者を止めないよう「制限なし」として扱います。
 */
final class RateLimiter
{
    private ?PDO $pdo = null;
    private ?string $secret = null;
    private readonly Closure $clock;

    /** @param Closure(): int|null $clock 現在時刻（テスト用に差し替え可能） */
    public function __construct(private readonly string $path, ?Closure $clock = null)
    {
        $this->clock = $clock ?? time(...);
    }

    public function tooManyAttempts(string $bucket, string $ip, int $max, int $window): bool
    {
        if ($max <= 0) {
            return false;
        }
        try {
            $statement = $this->db()->prepare('SELECT COUNT(*) FROM attempts WHERE bucket = ? AND client = ? AND at > ?');
            $statement->execute([$bucket, $this->clientKey($ip), ($this->clock)() - $window]);

            return (int) $statement->fetchColumn() >= $max;
        } catch (Throwable $e) {
            error_log('[qryno] rate limiter unavailable: ' . $e->getMessage());

            return false;
        }
    }

    public function hit(string $bucket, string $ip, int $window): void
    {
        try {
            $db = $this->db();
            $now = ($this->clock)();
            $db->prepare('INSERT INTO attempts (bucket, client, at) VALUES (?, ?, ?)')
                ->execute([$bucket, $this->clientKey($ip), $now]);
            $db->prepare('DELETE FROM attempts WHERE bucket = ? AND at <= ?')
                ->execute([$bucket, $now - $window]);
        } catch (Throwable $e) {
            error_log('[qryno] rate limiter unavailable: ' . $e->getMessage());
        }
    }

    public function clear(string $bucket, string $ip): void
    {
        try {
            $this->db()->prepare('DELETE FROM attempts WHERE bucket = ? AND client = ?')
                ->execute([$bucket, $this->clientKey($ip)]);
        } catch (Throwable $e) {
            error_log('[qryno] rate limiter unavailable: ' . $e->getMessage());
        }
    }

    /** IPv4は1アドレス、IPv6は/64単位でまとめたキー */
    private function clientKey(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            $material = $ip;
        } elseif (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10) . "\xFF\xFF")) {
            $material = substr($packed, 12); // IPv4射影アドレス
        } elseif (strlen($packed) === 16) {
            $material = substr($packed, 0, 8);
        } else {
            $material = $packed;
        }

        return hash_hmac('sha256', $material, $this->secret());
    }

    private function secret(): string
    {
        if ($this->secret === null) {
            $db = $this->db();
            $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES ('secret', ?)")
                ->execute([bin2hex(random_bytes(32))]);
            $this->secret = (string) $db->query("SELECT value FROM settings WHERE key = 'secret'")->fetchColumn();
        }

        return $this->secret;
    }

    private function db(): PDO
    {
        if ($this->pdo === null) {
            $dir = dirname($this->path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $this->path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->exec('CREATE TABLE IF NOT EXISTS attempts (bucket TEXT NOT NULL, client TEXT NOT NULL, at INTEGER NOT NULL)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS attempts_lookup ON attempts (bucket, client, at)');
            $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
            $this->pdo = $pdo;
        }

        return $this->pdo;
    }
}
