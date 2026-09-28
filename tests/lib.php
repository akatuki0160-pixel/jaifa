<?php

declare(strict_types=1);

/**
 * 依存ライブラリなしの簡易テスト基盤
 */

final class AssertionFailed extends RuntimeException
{
}

final class Tests
{
    /** @var list<array{string, callable}> */
    private static array $tests = [];
    /** @var list<callable> */
    private static array $cleanups = [];

    public static function add(string $name, callable $test): void
    {
        self::$tests[] = [$name, $test];
    }

    public static function onFinish(callable $cleanup): void
    {
        self::$cleanups[] = $cleanup;
    }

    public static function run(string $filter = ''): int
    {
        $passed = 0;
        $failed = [];
        foreach (self::$tests as [$name, $test]) {
            if ($filter !== '' && !str_contains($name, $filter)) {
                continue;
            }
            try {
                $test();
                $passed++;
                echo "  ok  {$name}\n";
            } catch (Throwable $e) {
                $failed[] = $name;
                echo "  NG  {$name}\n      " . str_replace("\n", "\n      ", $e->getMessage()) . "\n";
                if (!$e instanceof AssertionFailed) {
                    echo '      at ' . $e->getFile() . ':' . $e->getLine() . "\n";
                }
            }
        }
        foreach (array_reverse(self::$cleanups) as $cleanup) {
            $cleanup();
        }
        echo sprintf("\n%d件成功 / %d件失敗\n", $passed, count($failed));

        return $failed === [] ? 0 : 1;
    }
}

function test(string $name, callable $test): void
{
    Tests::add($name, $test);
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(trim($message . "\n期待値: " . var_export($expected, true) . "\n実際値: " . var_export($actual, true)));
    }
}

function assertTrue(bool $condition, string $message = '条件が成り立ちません'): void
{
    if (!$condition) {
        throw new AssertionFailed($message);
    }
}

function assertContains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new AssertionFailed(trim($message . "\n「{$needle}」が含まれていません:\n" . mb_strimwidth($haystack, 0, 600, '…')));
    }
}

function assertNotContains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new AssertionFailed(trim($message . "\n「{$needle}」が含まれています"));
    }
}

/** テストごとの一時フォルダ（終了時に削除） */
function tempDir(): string
{
    $dir = sys_get_temp_dir() . '/qryno-test-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    Tests::onFinish(static fn () => removeDir($dir));

    return $dir;
}

function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

/** UTF-8の文字列をCSVファイルに書き出します（$encoding で変換可） */
function writeCsv(string $content, string $encoding = 'UTF-8', string $prefix = ''): string
{
    $file = tempDir() . '/members.csv';
    $bytes = $encoding === 'UTF-8' ? $content : mb_convert_encoding($content, $encoding, 'UTF-8');
    file_put_contents($file, $prefix . $bytes);

    return $file;
}

function masters(): Qryno\Masters
{
    return Qryno\Masters::fromFile(dirname(__DIR__) . '/config/masters.php');
}

function importer(string $dataDir, float $shrinkGuardRatio = 0.5): Qryno\MemberImporter
{
    $config = require dirname(__DIR__) . '/config/config.php';

    return new Qryno\MemberImporter(masters(), $dataDir, $config['import']['columns'], 1000, $shrinkGuardRatio);
}
