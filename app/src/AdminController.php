<?php

declare(strict_types=1);

namespace Qryno;

use Throwable;

/** 会員データ管理画面（admin.php）: ログイン・CSV取込・取込状況の表示 */
final class AdminController
{
    private const LOGIN_KEY = 'qryno_admin';
    private const FLASH_KEY = 'qryno_flash';
    private const REPORT_KEY = 'qryno_report';

    public function __construct(private readonly App $app)
    {
    }

    public function handle(): void
    {
        $hash = (string) $this->app->config->get('admin.password_hash', '');
        if ($hash === '') {
            $this->render('admin/disabled', [], 403);

            return;
        }
        Http::startSession($this->app->sessionName());

        if (Http::isPost() && $_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            // post_max_size を超えると PHP は送信内容をすべて破棄する
            $this->flash(sprintf('送信サイズがサーバーの上限（post_max_size = %s）を超えました。', ini_get('post_max_size')));
            Http::redirect('admin.php');
        }
        if (Http::isPost() && !Http::checkCsrf(Http::post('token'))) {
            $this->flash('ページの有効期限が切れました。もう一度操作してください。');
            Http::redirect('admin.php');
        }

        $action = Http::isPost() ? Http::post('action') : (is_string($_GET['action'] ?? null) ? $_GET['action'] : '');

        if (!$this->isLoggedIn()) {
            if ($action === 'login' && Http::isPost()) {
                $this->login($hash);
            } else {
                $this->render('admin/login', ['error' => $this->takeFlash()]);
            }

            return;
        }

        match (true) {
            $action === 'logout' && Http::isPost() => $this->logout(),
            $action === 'import' && Http::isPost() => $this->import(),
            $action === 'template' => $this->downloadTemplate(),
            default => $this->dashboard(),
        };
    }

    private function login(string $hash): void
    {
        $limiter = $this->app->rateLimiter();
        $ip = Http::clientIp();
        $max = (int) $this->app->config->get('rate_limit.admin_login.max', 5);
        $window = (int) $this->app->config->get('rate_limit.admin_login.window', 900);

        if ($limiter->tooManyAttempts('admin_login', $ip, $max, $window)) {
            $this->render('admin/login', ['error' => 'ログインの失敗が続いたため、しばらくログインできません。時間をおいてからお試しください。'], 429);

            return;
        }
        if (!password_verify(Http::post('password'), $hash)) {
            $limiter->hit('admin_login', $ip, $window);
            $this->render('admin/login', ['error' => 'パスワードが正しくありません。']);

            return;
        }

        $limiter->clear('admin_login', $ip);
        session_regenerate_id(true);
        unset($_SESSION['csrf']);
        $_SESSION[self::LOGIN_KEY] = ['last_seen' => time()];
        Http::redirect('admin.php');
    }

    private function logout(): never
    {
        unset($_SESSION[self::LOGIN_KEY], $_SESSION[self::REPORT_KEY]);
        session_regenerate_id(true);
        Http::redirect('admin.php');
    }

    private function isLoggedIn(): bool
    {
        $login = $_SESSION[self::LOGIN_KEY] ?? null;
        if (!is_array($login)) {
            return false;
        }
        $timeout = (int) $this->app->config->get('admin.idle_timeout', 1800);
        if ((int) ($login['last_seen'] ?? 0) + $timeout < time()) {
            unset($_SESSION[self::LOGIN_KEY]);
            $this->flash('一定時間操作がなかったため、ログアウトしました。');

            return false;
        }
        $_SESSION[self::LOGIN_KEY]['last_seen'] = time();

        return true;
    }

    private function import(): never
    {
        $file = $_FILES['csv'] ?? null;
        $error = $this->uploadError($file);
        if ($error !== null) {
            $this->flash($error);
            Http::redirect('admin.php');
        }

        $report = $this->app->importer()->import(
            $file['tmp_name'],
            self::displayFileName((string) $file['name']),
            dryRun: Http::post('dry_run') === '1',
            force: Http::post('force') === '1',
            operator: 'admin ' . Http::clientIp(),
        );
        $_SESSION[self::REPORT_KEY] = $report->toArray();
        Http::redirect('admin.php');
    }

    private function uploadError(mixed $file): ?string
    {
        if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
            return 'CSVファイルを選択してください。';
        }
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return 'CSVファイルを選択してください。';
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return sprintf('ファイルサイズがサーバーの上限（%s）を超えています。', self::formatBytes($this->uploadLimit()));
            case UPLOAD_ERR_PARTIAL:
                return 'ファイルの送信が途中で切れました。もう一度お試しください。';
            default:
                return sprintf('ファイルを受け取れませんでした（エラーコード %d）。', $file['error']);
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            return 'ファイルを受け取れませんでした。';
        }
        $size = (int) $file['size'];
        $max = (int) $this->app->config->get('import.max_file_size', 30 * 1024 * 1024);
        if ($size === 0) {
            return 'ファイルが空です。';
        }
        if ($size > $max) {
            return sprintf('ファイルサイズが上限（%s）を超えています。', self::formatBytes($max));
        }

        return null;
    }

    private function dashboard(): void
    {
        try {
            $meta = $this->app->directory()->meta();
            $unreadable = false;
        } catch (Throwable $e) {
            // 壊れていても、取り込み直して復旧できるよう画面は表示する
            error_log('[qryno] could not read member database: ' . $e->getMessage());
            $meta = [];
            $unreadable = true;
        }
        $report = is_array($_SESSION[self::REPORT_KEY] ?? null) ? ImportReport::fromArray($_SESSION[self::REPORT_KEY]) : null;
        unset($_SESSION[self::REPORT_KEY]);

        $this->render('admin/dashboard', [
            'meta' => $meta,
            'unreadable' => $unreadable,
            'mastersChanged' => $meta !== [] && ($meta['masters_fingerprint'] ?? '') !== $this->app->masters()->fingerprint(),
            'report' => $report,
            'error' => $this->takeFlash(),
            'history' => $this->app->importer()->history(10),
            'columns' => (array) $this->app->config->get('import.columns', []),
            'uploadLimit' => self::formatBytes($this->uploadLimit()),
            'uploadLimitLow' => $this->uploadLimit() < 8 * 1024 * 1024,
        ]);
    }

    /** 実際にアップロードできる最大サイズ（PHP設定とシステム設定の小さい方） */
    private function uploadLimit(): int
    {
        $limits = array_filter([
            self::iniBytes((string) ini_get('upload_max_filesize')),
            self::iniBytes((string) ini_get('post_max_size')),
            (int) $this->app->config->get('import.max_file_size', 0),
        ], static fn (int $limit): bool => $limit > 0);

        return $limits === [] ? 0 : min($limits);
    }

    /** php.ini のサイズ表記（"2M" など）をバイト数に */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 * 1024 * 1024,
            'M' => $number * 1024 * 1024,
            'K' => $number * 1024,
            default => $number,
        };
    }

    private function downloadTemplate(): void
    {
        Http::sendSecurityHeaders();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="members_template.csv"');
        echo "\xEF\xBB\xBF会員番号,所属協会,姓,名,会社名\r\n";
    }

    /** @param array<string, mixed> $vars */
    private function render(string $template, array $vars = [], int $status = 200): void
    {
        Http::sendHtmlHeaders($status);
        $vars += [
            'token' => session_status() === PHP_SESSION_ACTIVE ? Http::csrfToken() : '',
            'loggedIn' => isset($_SESSION[self::LOGIN_KEY]),
        ];
        echo $this->app->view()->page($template, $vars, 'admin/layout');
    }

    private function flash(string $message): void
    {
        $_SESSION[self::FLASH_KEY] = $message;
    }

    private function takeFlash(): string
    {
        $message = $_SESSION[self::FLASH_KEY] ?? '';
        unset($_SESSION[self::FLASH_KEY]);

        return is_string($message) ? $message : '';
    }

    /** 記録・表示用のファイル名（パスや制御文字を除く） */
    private static function displayFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\p{Cc}\p{Cf}]/u', '', mb_scrub($name, 'UTF-8'));

        return mb_substr($name !== '' ? $name : 'upload.csv', 0, 100);
    }

    private static function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024 ? round($bytes / 1024 / 1024, 1) . 'MB' : max(1, (int) round($bytes / 1024)) . 'KB';
    }
}
