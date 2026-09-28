<?php

declare(strict_types=1);

namespace Qryno;

use Generator;

/**
 * CSV/TSVの読み込み
 *
 * 文字コード（UTF-8・BOM付きUTF-8・Shift_JIS(CP932)・UTF-16）と
 * 区切り文字（カンマ・タブ）を自動判別し、UTF-8の行として返します。
 */
final class CsvReader
{
    private function __construct(
        private readonly string $content,
        public readonly string $encoding,
        public readonly string $delimiter,
    ) {
    }

    public static function fromFile(string $path): self
    {
        $bytes = is_file($path) ? file_get_contents($path) : false;
        if ($bytes === false) {
            throw new ImportException('ファイルを読み込めませんでした。');
        }

        return self::fromString($bytes);
    }

    public static function fromString(string $bytes): self
    {
        [$content, $encoding] = self::toUtf8($bytes);
        // 改行がCRだけのファイル（古いMac形式）
        if (!str_contains($content, "\n") && str_contains($content, "\r")) {
            $content = str_replace("\r", "\n", $content);
        }

        return new self($content, $encoding, self::detectDelimiter($content));
    }

    /**
     * 行番号（Excelの行番号と同じく1始まり）=> 項目の配列 を順に返します。
     * 空行は空配列になります。
     *
     * @return Generator<int, list<string>>
     */
    public function rows(): Generator
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->content);
        rewind($stream);
        $number = 0;
        try {
            while (($row = fgetcsv($stream, null, $this->delimiter, '"', '')) !== false) {
                $number++;
                yield $number => $row === [null] ? [] : array_map('strval', $row);
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return array{0: string, 1: string} [UTF-8の内容, 判別した文字コード] */
    private static function toUtf8(string $bytes): array
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $body = substr($bytes, 3);
            if (!mb_check_encoding($body, 'UTF-8')) {
                throw new ImportException('UTF-8として読めない文字が含まれています。ファイルが壊れていないか確認してください。');
            }

            return [$body, 'UTF-8（BOM付き）'];
        }
        if (str_starts_with($bytes, "\xFF\xFE")) {
            return [mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE'), 'UTF-16LE'];
        }
        if (str_starts_with($bytes, "\xFE\xFF")) {
            return [mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE'), 'UTF-16BE'];
        }
        if (mb_check_encoding($bytes, 'UTF-8')) {
            return [$bytes, 'UTF-8'];
        }
        if (mb_check_encoding($bytes, 'CP932')) {
            return [mb_convert_encoding($bytes, 'UTF-8', 'CP932'), 'Shift_JIS（CP932）'];
        }

        throw new ImportException('文字コードを判別できませんでした。UTF-8 または Shift_JIS で保存したCSVを指定してください。');
    }

    /** 先頭の数行でタブとカンマの多い方を区切り文字とみなします */
    private static function detectDelimiter(string $content): string
    {
        $head = implode("\n", array_slice(explode("\n", substr($content, 0, 8192)), 0, 10));

        return substr_count($head, "\t") > substr_count($head, ',') ? "\t" : ',';
    }
}
