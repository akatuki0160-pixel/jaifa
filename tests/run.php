<?php

/**
 * テストの実行
 *
 *   php tests/run.php            すべて実行
 *   php tests/run.php 取込        名前に「取込」を含むテストだけ実行
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false; // @ で抑制されたもの
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// 環境変数で指定された追加設定は使わない（各テストは一時フォルダで動く）
putenv('QRYNO_CONFIG=');
$app = require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/lib.php';

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

exit(Tests::run($argv[1] ?? ''));
