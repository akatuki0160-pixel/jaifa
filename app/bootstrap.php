<?php

declare(strict_types=1);

/**
 * 会員番号検索システム 共通初期化
 *
 *   $app = require __DIR__ . '/../app/bootstrap.php';
 */

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('このシステムには PHP 8.1 以上が必要です。');
}
foreach (['mbstring', 'pdo_sqlite'] as $extension) {
    if (!extension_loaded($extension)) {
        http_response_code(500);
        exit("PHP拡張 {$extension} が有効になっていません。");
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Qryno\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (!function_exists('h')) {
    /** HTMLエスケープ */
    function h(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

return Qryno\App::boot(dirname(__DIR__));
