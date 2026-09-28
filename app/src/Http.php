<?php

declare(strict_types=1);

namespace Qryno;

use RuntimeException;

/** リクエスト・レスポンス・セッションの補助 */
final class Http
{
    /** スクリプト・外部読み込みを一切使わない前提のCSP */
    private const CONTENT_SECURITY_POLICY = "default-src 'none'; style-src 'self'; img-src 'self'; "
        . "form-action 'self'; frame-ancestors 'none'; base-uri 'none'";

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** HTTPSでのアクセスか（Cookie の Secure 属性の判定用。リバースプロキシ配下も考慮） */
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /** POST値を文字列で取り出します（配列などが送られた場合は空文字） */
    public static function post(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    public static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public static function startSession(string $name): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_cache_limiter('');
        if (!session_start()) {
            throw new RuntimeException('セッションを開始できません（session.save_path の書き込み権限を確認してください）。');
        }
    }

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public static function checkCsrf(string $token): bool
    {
        return isset($_SESSION['csrf']) && is_string($_SESSION['csrf']) && $token !== ''
            && hash_equals($_SESSION['csrf'], $token);
    }

    /** 画面表示用の共通ヘッダー。結果画面を含め、ブラウザ等に保存させません。 */
    public static function sendHtmlHeaders(int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        self::sendSecurityHeaders();
    }

    public static function sendSecurityHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Content-Security-Policy: ' . self::CONTENT_SECURITY_POLICY);
    }

    public static function redirect(string $location): never
    {
        http_response_code(303);
        self::sendSecurityHeaders();
        header('Location: ' . $location);
        exit;
    }
}
