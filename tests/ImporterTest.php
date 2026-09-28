<?php

declare(strict_types=1);

use Qryno\ImportReport;
use Qryno\MemberDirectory;

const HEADER = "会員番号,所属協会,姓,名,会社名\n";

/** 取込後の検索（入力はフォームと同じ表記） */
function lookup(string $dataDir, string $kyokai, string $sei, string $mei, string $company): string
{
    $masters = masters();
    $result = (new MemberDirectory($dataDir . '/members.sqlite'))->find(
        (string) $masters->resolveKyokai($kyokai),
        (string) $masters->resolveCompany($company),
        Qryno\Text::normalize($sei),
        Qryno\Text::normalize($mei),
    );

    return $result->status . ($result->memberNo !== null ? ':' . $result->memberNo : '');
}

test('取込: Shift_JISのCSVを取り込んで検索できる', static function (): void {
    $dir = tempDir();
    $csv = writeCsv(HEADER . "00012345,130　東京,髙橋,一郎,ＳＯＭＰＯひまわり生命保険株式会社\r\n", 'CP932');
    $report = importer($dir)->import($csv, 'members.csv');
    assertSame(ImportReport::APPLIED, $report->status, (string) $report->failure);
    assertSame(1, $report->importedRows);
    assertSame('Shift_JIS（CP932）', $report->encoding);
    assertSame('found:00012345', lookup($dir, '130　東京', '髙橋', '一郎', 'ＳＯＭＰＯひまわり生命保険株式会社'));
    assertSame('found:00012345', lookup($dir, '130　東京', ' 髙橋　', '一郎', 'SOMPOひまわり生命保険株式会社'), '前後の空白・全角半角');
    assertSame('not_found', lookup($dir, '130　東京', '高橋', '一郎', 'ＳＯＭＰＯひまわり生命保険株式会社'), '字体違いは不一致');
    assertSame('not_found', lookup($dir, '140　神奈川県', '髙橋', '一郎', 'ＳＯＭＰＯひまわり生命保険株式会社'), '協会違い');
    assertSame('not_found', lookup($dir, '130　東京', '髙橋', '一', 'ＳＯＭＰＯひまわり生命保険株式会社'), '部分一致はしない');
});

test('取込: 所属協会は「130」「東京」だけの表記でも取り込める', static function (): void {
    $dir = tempDir();
    $csv = writeCsv(HEADER . "1,130,山田,太郎,代理店\n2,東京,佐藤,花子,代理店\n3,10,鈴木,一郎,代理店\n");
    $report = importer($dir)->import($csv, 'm.csv');
    assertSame(3, $report->importedRows);
    assertSame('found:2', lookup($dir, '130　東京', '佐藤', '花子', '代理店'));
    assertSame('found:3', lookup($dir, '010　札幌', '鈴木', '一郎', '代理店'));
});

test('取込: 見出しの別名・列の順序・余分な列・見出し前の行に対応', static function (): void {
    $dir = tempDir();
    $csv = writeCsv("会員名簿（2026年9月）\n\n備考,所属会社名,氏名（名）,氏名（姓）,協会,会員ＮＯ\nx,代理店,太郎,山田,130,A-1\n");
    $report = importer($dir)->import($csv, 'm.csv');
    assertSame(ImportReport::APPLIED, $report->status, (string) $report->failure);
    assertSame(3, $report->headerRow);
    assertSame('found:A-1', lookup($dir, '130　東京', '山田', '太郎', '代理店'));
});

test('取込: 姓・名の列がなければ「氏名」を空白で分割', static function (): void {
    $dir = tempDir();
    $csv = writeCsv("会員番号,所属協会,氏名,会社名\n1,130,山田　太郎,代理店\n2,130,山田太郎,代理店\n");
    $report = importer($dir)->import($csv, 'm.csv');
    assertSame(1, $report->importedRows);
    assertSame(1, $report->errorRows);
    assertContains('姓と名に分けられません', $report->errors[0]['message']);
    assertSame('found:1', lookup($dir, '130　東京', '山田', '太郎', '代理店'));
});

test('取込: 不備のある行は理由と行番号を記録して取り込まない', static function (): void {
    $dir = tempDir();
    $csv = writeCsv(HEADER . "1,999,山田,太郎,代理店\n,130,佐藤,花子,代理店\n3,130,,一郎,日本生命\n4,130,田中,次郎,代理店\n\n");
    $report = importer($dir)->import($csv, 'm.csv');
    assertSame(ImportReport::APPLIED, $report->status);
    assertSame(4, $report->dataRows, '空行は数えない');
    assertSame(1, $report->importedRows);
    assertSame(3, $report->errorRows);
    assertSame(2, $report->errors[0]['row']);
    assertContains('所属協会「999」がマスタにありません', $report->errors[0]['message']);
    assertContains('会員番号が空です', $report->errors[1]['message']);
    assertContains('姓が空です', $report->errors[2]['message']);
    assertContains('会社名「日本生命」がマスタにありません', $report->errors[2]['message']);
});

test('取込: 見出しがないCSVは中止し、足りない列を知らせる', static function (): void {
    $dir = tempDir();
    $report = importer($dir)->import(writeCsv("会員番号,所属協会,姓,名\n1,130,山田,太郎\n"), 'm.csv');
    assertSame(ImportReport::FAILED, $report->status);
    assertContains('見つからない列: 会社名', (string) $report->failure);
    assertTrue(!is_file($dir . '/members.sqlite'));
});

test('取込: 取り込める行が0件なら中止', static function (): void {
    $report = importer(tempDir())->import(writeCsv(HEADER . "1,999,山田,太郎,代理店\n"), 'm.csv');
    assertSame(ImportReport::FAILED, $report->status);
    assertSame(1, $report->errorRows);
});

test('取込: 同一条件で会員番号が異なる会員は警告し、検索では特定不可', static function (): void {
    $dir = tempDir();
    $csv = writeCsv(HEADER . "1,130,小林,大輔,代理店\n2,130,小林,大輔,代理店\n3,130,山田,太郎,代理店\n3,130,山田,太郎,代理店\n");
    $report = importer($dir)->import($csv, 'm.csv');
    $messages = implode("\n", array_column($report->warnings, 'message'));
    assertContains('会員番号が異なる会員が 1 組', $messages);
    assertContains('130　東京 / 小林 大輔 / 代理店（2・3行目）', implode("\n", $report->warnings[0]['items']));
    assertContains('同じ会員番号が複数の行にあります（1件）', $messages);
    assertSame('multiple', lookup($dir, '130　東京', '小林', '大輔', '代理店'));
    assertSame('found:3', lookup($dir, '130　東京', '山田', '太郎', '代理店'), '同じ会員の重複行は1件として扱う');
});

test('取込: 会員番号の桁数のばらつき（Excelで先頭0が消えた疑い）を警告', static function (): void {
    $report = importer(tempDir())->import(writeCsv(HEADER . "00123,130,山田,太郎,代理店\n123,130,佐藤,花子,代理店\n"), 'm.csv');
    assertContains('桁数がそろっていません（3〜5桁）', implode("\n", array_column($report->warnings, 'message')));
});

test('取込: 件数が大幅に減る取込は止める（強制・検証のみの扱いも確認）', static function (): void {
    $dir = tempDir();
    $many = HEADER;
    for ($i = 1; $i <= 10; $i++) {
        $many .= "{$i},130,山田,太郎{$i},代理店\n";
    }
    importer($dir)->import(writeCsv($many), 'many.csv');
    $few = writeCsv(HEADER . "99,130,佐藤,花子,代理店\n");

    $blocked = importer($dir)->import($few, 'few.csv');
    assertSame(ImportReport::FAILED, $blocked->status);
    assertTrue($blocked->shrinkBlocked);
    assertSame('found:1', lookup($dir, '130　東京', '山田', '太郎1', '代理店'), '以前のデータのまま');

    $dryRun = importer($dir)->import($few, 'few.csv', dryRun: true);
    assertSame(ImportReport::VALIDATED, $dryRun->status);
    assertContains('大幅に少なく', implode("\n", array_column($dryRun->warnings, 'message')));
    assertSame('found:1', lookup($dir, '130　東京', '山田', '太郎1', '代理店'), '検証のみでは置き換えない');

    $forced = importer($dir)->import($few, 'few.csv', force: true);
    assertSame(ImportReport::APPLIED, $forced->status);
    assertSame(10, $forced->previousCount);
    assertSame('not_found', lookup($dir, '130　東京', '山田', '太郎1', '代理店'));
    assertSame('found:99', lookup($dir, '130　東京', '佐藤', '花子', '代理店'));
});

test('取込: 失敗しても以前のデータで検索でき、一時ファイルも残らない', static function (): void {
    $dir = tempDir();
    importer($dir)->import(writeCsv(HEADER . "1,130,山田,太郎,代理店\n"), 'ok.csv');
    $report = importer($dir)->import(writeCsv("壊れたファイル\n"), 'bad.csv');
    assertSame(ImportReport::FAILED, $report->status);
    assertSame('found:1', lookup($dir, '130　東京', '山田', '太郎', '代理店'));
    assertSame([], glob($dir . '/members.sqlite.tmp-*'));
    $meta = (new MemberDirectory($dir . '/members.sqlite'))->meta();
    assertSame('ok.csv', $meta['source_name']);
    assertSame('1', $meta['row_count']);
    assertSame(masters()->fingerprint(), $meta['masters_fingerprint']);
});

test('取込: 取込履歴を新しい順に記録', static function (): void {
    $dir = tempDir();
    importer($dir)->import(writeCsv(HEADER . "1,130,山田,太郎,代理店\n"), 'first.csv', operator: 'test');
    importer($dir)->import(writeCsv("x\n"), 'second.csv', operator: 'test');
    $history = importer($dir)->history();
    assertSame(['second.csv', 'first.csv'], array_column($history, 'source_name'));
    assertSame([ImportReport::FAILED, ImportReport::APPLIED], array_column($history, 'status'));
    assertSame('test', $history[0]['operator']);
});

test('取込: 取込中は別の取込を受け付けない', static function (): void {
    $dir = tempDir();
    $lock = fopen($dir . '/import.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        $report = importer($dir)->import(writeCsv(HEADER . "1,130,山田,太郎,代理店\n"), 'm.csv');
        assertSame(ImportReport::FAILED, $report->status);
        assertContains('別の取込処理が実行中', (string) $report->failure);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

test('取込: 既存のデータベースが壊れていても取込で置き換えられる', static function (): void {
    $dir = tempDir();
    file_put_contents($dir . '/members.sqlite', str_repeat('broken', 100));
    $log = ini_set('error_log', $dir . '/error.log');
    try {
        $report = importer($dir)->import(writeCsv(HEADER . "1,130,山田,太郎,代理店\n"), 'm.csv');
    } finally {
        ini_set('error_log', (string) $log);
    }
    assertSame(ImportReport::APPLIED, $report->status, (string) $report->failure);
    assertSame(0, $report->previousCount);
    assertSame('found:1', lookup($dir, '130　東京', '山田', '太郎', '代理店'));
});
