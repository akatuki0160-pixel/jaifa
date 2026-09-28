<?php

declare(strict_types=1);

use Qryno\RateLimiter;

test('回数制限: 上限に達したら止め、時間が経てば解除', static function (): void {
    $now = 1_000_000;
    $limiter = new RateLimiter(tempDir() . '/runtime.sqlite', static function () use (&$now): int {
        return $now;
    });
    for ($i = 0; $i < 3; $i++) {
        assertTrue(!$limiter->tooManyAttempts('search', '203.0.113.5', 3, 600));
        $limiter->hit('search', '203.0.113.5', 600);
    }
    assertTrue($limiter->tooManyAttempts('search', '203.0.113.5', 3, 600));
    assertTrue(!$limiter->tooManyAttempts('search', '203.0.113.6', 3, 600), '別の接続元は影響なし');
    assertTrue(!$limiter->tooManyAttempts('admin_login', '203.0.113.5', 3, 600), '別の種類は影響なし');
    $now += 601;
    assertTrue(!$limiter->tooManyAttempts('search', '203.0.113.5', 3, 600));
});

test('回数制限: IPv6は/64単位、IPv4射影アドレスはIPv4として扱う', static function (): void {
    $limiter = new RateLimiter(tempDir() . '/runtime.sqlite');
    $limiter->hit('search', '2001:db8:1:2::10', 600);
    assertTrue($limiter->tooManyAttempts('search', '2001:db8:1:2:ffff::1', 1, 600), '同じ/64');
    assertTrue(!$limiter->tooManyAttempts('search', '2001:db8:1:3::10', 1, 600), '別の/64');

    $limiter->hit('search', '::ffff:198.51.100.7', 600);
    assertTrue($limiter->tooManyAttempts('search', '198.51.100.7', 1, 600));
    assertTrue(!$limiter->tooManyAttempts('search', '::ffff:198.51.100.8', 1, 600));
});

test('回数制限: 解除・無効化・記録できない場合は止めない', static function (): void {
    $limiter = new RateLimiter(tempDir() . '/runtime.sqlite');
    $limiter->hit('admin_login', '192.0.2.1', 600);
    assertTrue($limiter->tooManyAttempts('admin_login', '192.0.2.1', 1, 600));
    $limiter->clear('admin_login', '192.0.2.1');
    assertTrue(!$limiter->tooManyAttempts('admin_login', '192.0.2.1', 1, 600));
    assertTrue(!$limiter->tooManyAttempts('search', '192.0.2.1', 0, 600), 'max=0 は無効');

    $dir = tempDir();
    touch($dir . '/not-a-directory');
    $broken = new RateLimiter($dir . '/not-a-directory/runtime.sqlite');
    $log = ini_set('error_log', $dir . '/error.log');
    set_error_handler(static fn (): bool => true);
    try {
        $broken->hit('search', '192.0.2.1', 600);
        assertTrue(!$broken->tooManyAttempts('search', '192.0.2.1', 1, 600));
    } finally {
        restore_error_handler();
        ini_set('error_log', (string) $log);
    }
});

test('回数制限: IPアドレスをそのまま保存しない', static function (): void {
    $path = tempDir() . '/runtime.sqlite';
    (new RateLimiter($path))->hit('search', '203.0.113.99', 600);
    assertNotContains('203.0.113.99', (string) file_get_contents($path));
});

test('回数制限: 保存先フォルダがなければ作る', static function (): void {
    $path = tempDir() . '/data/sub/runtime.sqlite';
    $limiter = new RateLimiter($path);
    $limiter->hit('admin_login', '192.0.2.1', 600);
    assertTrue(is_file($path));
    assertTrue($limiter->tooManyAttempts('admin_login', '192.0.2.1', 1, 600));
});
