<?php

/**
 * 会員CSVの取込（コマンド版）
 *
 *   php bin/import.php 会員データ.csv            取り込んで会員データを置き換える
 *   php bin/import.php 会員データ.csv --dry-run  検証だけ行う（置き換えない）
 *   php bin/import.php 会員データ.csv --force    件数が大幅に減っても置き換える
 *
 * 終了コード: 0 = 成功（検証のみを含む）、1 = 中止、2 = 引数の誤り
 */

declare(strict_types=1);

use Qryno\ImportReport;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$app = require dirname(__DIR__) . '/app/bootstrap.php';

$args = array_slice($argv, 1);
$options = array_values(array_filter($args, static fn (string $arg): bool => str_starts_with($arg, '--')));
$files = array_values(array_filter($args, static fn (string $arg): bool => !str_starts_with($arg, '--')));
if (count($files) !== 1 || array_diff($options, ['--dry-run', '--force']) !== []) {
    fwrite(STDERR, "使い方: php bin/import.php 会員データ.csv [--dry-run] [--force]\n");
    exit(2);
}

$user = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '') : (getenv('USER') ?: '');
$report = $app->importer()->import(
    $files[0],
    basename($files[0]),
    dryRun: in_array('--dry-run', $options, true),
    force: in_array('--force', $options, true),
    operator: trim('cli ' . $user),
);

$number = static fn (int $value): string => number_format($value);
$lines = [
    '結果: ' . [
        ImportReport::APPLIED => '取込完了（会員データを置き換えました）',
        ImportReport::VALIDATED => '検証のみ（会員データは置き換えていません）',
        ImportReport::FAILED => '中止（会員データは以前のままです）',
    ][$report->status],
];
if ($report->failure !== null) {
    $lines[] = '理由: ' . $report->failure;
}
if ($report->encoding !== '') {
    $lines[] = sprintf('ファイル: %s（%sバイト、%s、%s区切り）', $report->sourceName, $number($report->fileSize), $report->encoding, $report->delimiter);
}
if ($report->headerRow > 0) {
    $lines[] = sprintf('見出し行: %d行目', $report->headerRow);
    $lines[] = sprintf('データ行数: %s行', $number($report->dataRows));
    $lines[] = sprintf('取込件数: %s件', $number($report->importedRows));
    $lines[] = sprintf('エラー行: %s行', $number($report->errorRows));
}
foreach ($report->errors as $error) {
    $lines[] = sprintf('  %d行目: %s', $error['row'], $error['message']);
}
if ($report->errorRows > count($report->errors)) {
    $lines[] = sprintf('  …ほか %s行', $number($report->errorRows - count($report->errors)));
}
foreach ($report->warnings as $warning) {
    $lines[] = '警告: ' . $warning['message'];
    foreach ($warning['items'] as $item) {
        $lines[] = '  - ' . $item;
    }
}
$lines[] = sprintf('取込前の件数: %s件', $number($report->previousCount));
$lines[] = sprintf('処理時間: %s秒', $report->seconds);

echo implode("\n", $lines), "\n";
exit($report->status === ImportReport::FAILED ? 1 : 0);
