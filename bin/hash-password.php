<?php

/**
 * 管理画面のパスワードハッシュを作ります。
 *
 *   php bin/hash-password.php
 *
 * 表示されたハッシュを config/config.local.php の admin.password_hash に設定してください。
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$interactive = stream_isatty(STDIN);

$read = static function (string $prompt) use ($interactive): string {
    if (!$interactive) {
        return rtrim((string) fgets(STDIN), "\r\n");
    }
    fwrite(STDOUT, $prompt);
    $canHide = DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
    if ($canHide) {
        @shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($canHide) {
        @shell_exec('stty echo');
    }
    fwrite(STDOUT, "\n");

    return rtrim((string) $line, "\r\n");
};

$password = $read('パスワード: ');
if ($interactive && $read('確認のためもう一度: ') !== $password) {
    fwrite(STDERR, "パスワードが一致しません。\n");
    exit(1);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "パスワードは12文字以上にしてください。\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($interactive) {
    echo "\nconfig/config.local.php に次のように設定してください:\n\n";
    echo "    'admin' => [\n        'password_hash' => '{$hash}',\n    ],\n\n";
} else {
    echo $hash, "\n";
}
