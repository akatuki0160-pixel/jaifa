<?php

declare(strict_types=1);

use Qryno\CsvReader;
use Qryno\ImportException;

$rows = static fn (CsvReader $csv): array => iterator_to_array($csv->rows());

test('CSV: Shift_JIS（CP932）を判別してUTF-8に変換', static function () use ($rows): void {
    $csv = CsvReader::fromString(mb_convert_encoding("姓,名\r\n髙橋,①太郎\r\n", 'CP932', 'UTF-8'));
    assertSame('Shift_JIS（CP932）', $csv->encoding);
    assertSame([1 => ['姓', '名'], 2 => ['髙橋', '①太郎']], $rows($csv));
});

test('CSV: BOM付きUTF-8・BOMなしUTF-8', static function () use ($rows): void {
    $bom = CsvReader::fromString("\xEF\xBB\xBF姓,名\n山田,太郎\n");
    assertSame('UTF-8（BOM付き）', $bom->encoding);
    assertSame(['姓', '名'], $rows($bom)[1], 'BOMが見出しに残らない');
    assertSame('UTF-8', CsvReader::fromString("姓,名\n")->encoding);
});

test('CSV: UTF-16LE（Excelの「Unicodeテキスト」）はタブ区切りとして読める', static function () use ($rows): void {
    $csv = CsvReader::fromString("\xFF\xFE" . mb_convert_encoding("姓\t名\r\n山田\t太郎\r\n", 'UTF-16LE', 'UTF-8'));
    assertSame('UTF-16LE', $csv->encoding);
    assertSame("\t", $csv->delimiter);
    assertSame(['山田', '太郎'], $rows($csv)[2]);
});

test('CSV: 引用符内のカンマ・改行・引用符', static function () use ($rows): void {
    $csv = CsvReader::fromString("a,b\n\"x,y\",\"1行目\n2行目\"\n\"say \"\"hi\"\"\",z\n");
    assertSame([1 => ['a', 'b'], 2 => ['x,y', "1行目\n2行目"], 3 => ['say "hi"', 'z']], $rows($csv));
});

test('CSV: 空行は空配列、改行がCRだけのファイルも読める', static function () use ($rows): void {
    assertSame([1 => ['a'], 2 => [], 3 => ['b']], $rows(CsvReader::fromString("a\n\nb\n")));
    assertSame([1 => ['a', 'b'], 2 => ['c', 'd']], $rows(CsvReader::fromString("a,b\rc,d\r")));
});

test('CSV: 判別できない文字コードは取込中止', static function (): void {
    try {
        CsvReader::fromString("\x81\x20\xFF\xFF");
        throw new AssertionFailed('例外が発生しませんでした');
    } catch (ImportException $e) {
        assertContains('文字コード', $e->getMessage());
    }
});
