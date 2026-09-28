<?php

declare(strict_types=1);

namespace Qryno;

use Throwable;

/** 設定と各部品の組み立て */
final class App
{
    private ?Masters $masters = null;
    private ?View $view = null;

    private function __construct(
        public readonly string $root,
        public readonly Config $config,
    ) {
    }

    public static function boot(string $root): self
    {
        $config = Config::load($root);
        mb_internal_encoding('UTF-8');
        date_default_timezone_set((string) $config->get('timezone', 'Asia/Tokyo'));

        $app = new self($root, $config);
        if (PHP_SAPI !== 'cli') {
            ini_set('display_errors', $config->get('debug') ? '1' : '0');
            set_exception_handler([$app, 'renderException']);
        }

        return $app;
    }

    public function dataDir(): string
    {
        $dir = rtrim((string) $this->config->get('data_dir', 'data'), '/\\');
        $absolute = str_starts_with($dir, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $dir) === 1;

        return $absolute ? $dir : $this->root . '/' . $dir;
    }

    public function masters(): Masters
    {
        return $this->masters ??= Masters::fromFile($this->root . '/config/masters.php');
    }

    public function directory(): MemberDirectory
    {
        return new MemberDirectory($this->dataDir() . '/members.sqlite');
    }

    public function importer(): MemberImporter
    {
        return new MemberImporter(
            $this->masters(),
            $this->dataDir(),
            (array) $this->config->get('import.columns', []),
            (int) $this->config->get('import.max_reported_errors', 1000),
            (float) $this->config->get('import.shrink_guard_ratio', 0.5),
        );
    }

    public function rateLimiter(): RateLimiter
    {
        return new RateLimiter($this->dataDir() . '/runtime.sqlite');
    }

    public function view(): View
    {
        $publicDir = PHP_SAPI === 'cli' || empty($_SERVER['SCRIPT_FILENAME'])
            ? $this->root . '/public'
            : dirname((string) $_SERVER['SCRIPT_FILENAME']);

        return $this->view ??= new View($this->root . '/app/views', $publicDir, [
            'site' => (array) $this->config->get('site', []),
            'contact' => (array) $this->config->get('contact', []),
        ]);
    }

    public function sessionName(): string
    {
        return (string) $this->config->get('session_name', 'QRYNOSESS');
    }

    /** 想定外のエラー: 記録して、利用者には詳細を出さずにエラー画面を表示します */
    public function renderException(Throwable $e): void
    {
        error_log('[qryno] ' . $e);
        if (!headers_sent()) {
            Http::sendHtmlHeaders(500);
        }
        try {
            echo $this->view()->page('error', ['detail' => $this->config->get('debug') ? (string) $e : '']);
        } catch (Throwable) {
            echo 'システムエラーが発生しました。';
        }
    }
}
