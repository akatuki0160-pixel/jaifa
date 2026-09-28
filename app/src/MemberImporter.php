<?php

declare(strict_types=1);

namespace Qryno;

use PDO;
use Throwable;

/**
 * 会員CSVを検索用データベース（SQLite）に取り込みます。
 *
 * 一時ファイルにデータベースを作り、成功したときだけ本番ファイルと入れ替えるため、
 * 取込中や取込失敗時も検索は以前のデータのまま使えます。
 */
final class MemberImporter
{
    /** 見出し行を探す先頭からの行数 */
    private const HEADER_SCAN_ROWS = 10;
    /** 警告ごとに表示する明細の最大件数 */
    private const WARNING_ITEMS = 100;
    private const FIELD_LABELS = [
        'member_no' => '会員番号',
        'kyokai' => '所属協会',
        'sei' => '姓',
        'mei' => '名',
        'name' => '氏名',
        'company' => '会社名',
    ];

    /** @param array<string, list<string>> $columnAliases 項目 => 見出し名の候補 */
    public function __construct(
        private readonly Masters $masters,
        private readonly string $dataDir,
        private readonly array $columnAliases,
        private readonly int $maxReportedErrors = 1000,
        private readonly float $shrinkGuardRatio = 0.5,
    ) {
    }

    public function targetPath(): string
    {
        return $this->dataDir . '/members.sqlite';
    }

    public function logPath(): string
    {
        return $this->dataDir . '/logs/import.log';
    }

    /**
     * @param string $file       取り込むCSVのパス
     * @param string $sourceName 記録用の元ファイル名
     * @param bool   $dryRun     true なら検証だけ行い、データは置き換えない
     * @param bool   $force      true なら件数が大幅に減っても置き換える
     * @param string $operator   記録用の実行者（例: "admin 203.0.113.1", "cli"）
     */
    public function import(string $file, string $sourceName, bool $dryRun = false, bool $force = false, string $operator = ''): ImportReport
    {
        $started = microtime(true);
        $report = new ImportReport();
        $report->sourceName = $sourceName;
        $lock = null;
        $temp = null;

        try {
            $this->prepareDataDir();
            $lock = $this->lock();
            $report->previousCount = $this->currentCount();

            $temp = $this->targetPath() . '.tmp-' . bin2hex(random_bytes(6));
            $this->buildDatabase($file, $temp, $report);

            $shrunk = $report->previousCount > 0
                && $report->importedRows < $report->previousCount * $this->shrinkGuardRatio;
            $shrinkMessage = sprintf(
                '取り込める件数（%s件）が現在の件数（%s件）より大幅に少なくなっています。',
                number_format($report->importedRows),
                number_format($report->previousCount)
            );

            if ($dryRun) {
                if ($shrunk) {
                    $report->addWarning($shrinkMessage . '置き換えるときは「件数が減っても置き換える」にチェックが必要です。');
                }
                $report->status = ImportReport::VALIDATED;
            } elseif ($shrunk && !$force) {
                $report->shrinkBlocked = true;
                throw new ImportException($shrinkMessage . '誤ったファイルでないか確認し、置き換える場合は「件数が減っても置き換える」にチェックして取り込んでください。');
            } else {
                if (!rename($temp, $this->targetPath())) {
                    throw new ImportException('データベースファイルを置き換えられませんでした。データ保存先の書き込み権限を確認してください。');
                }
                $temp = null;
                $report->status = ImportReport::APPLIED;
            }
        } catch (ImportException $e) {
            $report->status = ImportReport::FAILED;
            $report->failure = $e->getMessage();
        } catch (Throwable $e) {
            $report->status = ImportReport::FAILED;
            $report->failure = '取込中にエラーが発生しました: ' . $e->getMessage();
            error_log('[qryno] import failed: ' . $e);
        } finally {
            if ($temp !== null && is_file($temp)) {
                @unlink($temp);
            }
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            $report->seconds = round(microtime(true) - $started, 2);
            $report->finishedAt = date('Y-m-d H:i:s');
        }

        $this->writeLog($report, $operator);

        return $report;
    }

    /**
     * 取込履歴（新しい順）
     *
     * @return list<array<string, mixed>>
     */
    public function history(int $limit = 10): array
    {
        if (!is_file($this->logPath())) {
            return [];
        }
        $lines = file($this->logPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $entries = [];
        foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function buildDatabase(string $file, string $temp, ImportReport $report): void
    {
        $report->fileSize = (int) filesize($file);
        $report->sha256 = (string) hash_file('sha256', $file);
        $csv = CsvReader::fromFile($file);
        $report->encoding = $csv->encoding;
        $report->delimiter = $csv->delimiter === "\t" ? 'タブ' : 'カンマ';

        $pdo = new PDO('sqlite:' . $temp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        try {
            MemberDirectory::createSchema($pdo);
            $pdo->beginTransaction();
            $this->insertRows($csv, $pdo, $report);
            MemberDirectory::createIndexes($pdo);
            $this->collectWarnings($pdo, $report);

            $meta = [
                'imported_at' => date('Y-m-d H:i:s'),
                'source_name' => $report->sourceName,
                'source_sha256' => $report->sha256,
                'source_size' => (string) $report->fileSize,
                'encoding' => $report->encoding,
                'row_count' => (string) $report->importedRows,
                'error_rows' => (string) $report->errorRows,
                'masters_fingerprint' => $this->masters->fingerprint(),
            ];
            $insertMeta = $pdo->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
            foreach ($meta as $key => $value) {
                $insertMeta->execute([$key, $value]);
            }
            $pdo->commit();
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    /** 現在の登録件数（データベースが壊れていて読めない場合も、取込で置き換えられるよう0件とみなす） */
    private function currentCount(): int
    {
        try {
            return (new MemberDirectory($this->targetPath()))->count();
        } catch (Throwable $e) {
            error_log('[qryno] could not read current member database: ' . $e->getMessage());

            return 0;
        }
    }

    private function insertRows(CsvReader $csv, PDO $pdo, ImportReport $report): void
    {
        $insert = $pdo->prepare(
            'INSERT INTO members (member_no, kyokai, company, sei, mei, row_no) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $columns = null;
        $firstRow = null;
        $scanned = 0;

        foreach ($csv->rows() as $rowNo => $fields) {
            if (self::isBlank($fields)) {
                continue;
            }
            if ($columns === null) {
                $firstRow ??= $fields;
                $columns = $this->detectColumns($fields);
                if ($columns !== null) {
                    $report->headerRow = $rowNo;
                    foreach ($columns as $field => $index) {
                        $report->columns[self::FIELD_LABELS[$field]] = trim($fields[$index]);
                    }
                } elseif (++$scanned >= self::HEADER_SCAN_ROWS) {
                    break;
                }
                continue;
            }

            $report->dataRows++;
            $errors = [];
            $record = $this->parseRow($fields, $columns, $errors);
            if ($record === null) {
                $report->addError($rowNo, implode('／', $errors), $this->maxReportedErrors);
                continue;
            }
            $record[] = $rowNo;
            $insert->execute($record);
            $report->importedRows++;
        }

        if ($columns === null) {
            throw new ImportException($this->headerNotFoundMessage($firstRow));
        }
        if ($report->importedRows === 0) {
            throw new ImportException('取り込める行がありませんでした。下のエラー内容を確認してください。');
        }
    }

    /**
     * 見出し行なら 項目 => 列番号 を返します。
     *
     * @param list<string> $header
     * @return array<string, int>|null
     */
    private function detectColumns(array $header): ?array
    {
        $columns = $this->matchColumns($header);
        if ($this->missingColumns($columns) !== []) {
            return null;
        }
        // 姓・名の列があればそれを使い、なければ氏名を分割する
        if (isset($columns['sei'], $columns['mei'])) {
            unset($columns['name']);
        } else {
            unset($columns['sei'], $columns['mei']);
        }

        return $columns;
    }

    /**
     * @param list<string> $header
     * @return array<string, int>
     */
    private function matchColumns(array $header): array
    {
        $positions = [];
        foreach ($header as $index => $name) {
            $key = self::headerKey($name);
            if ($key !== '' && !isset($positions[$key])) {
                $positions[$key] = $index;
            }
        }
        $columns = [];
        foreach ($this->columnAliases as $field => $aliases) {
            foreach ($aliases as $alias) {
                $key = self::headerKey($alias);
                if (isset($positions[$key])) {
                    $columns[$field] = $positions[$key];
                    break;
                }
            }
        }

        return $columns;
    }

    /**
     * @param array<string, int> $columns
     * @return list<string> 見つからない項目名
     */
    private function missingColumns(array $columns): array
    {
        $missing = [];
        foreach (['member_no', 'kyokai'] as $field) {
            if (!isset($columns[$field])) {
                $missing[] = self::FIELD_LABELS[$field];
            }
        }
        if (!isset($columns['name']) && !isset($columns['sei'], $columns['mei'])) {
            $missing[] = '姓・名（または氏名）';
        }
        if (!isset($columns['company'])) {
            $missing[] = self::FIELD_LABELS['company'];
        }

        return $missing;
    }

    /** @param list<string>|null $firstRow */
    private function headerNotFoundMessage(?array $firstRow): string
    {
        if ($firstRow === null) {
            return 'ファイルにデータがありません。';
        }
        $missing = $this->missingColumns($this->matchColumns($firstRow));

        return sprintf(
            '見出し行が見つかりません（見つからない列: %s）。1行目に「会員番号,所属協会,姓,名,会社名」の見出しを入れてください。先頭行の内容: 「%s」',
            implode('、', $missing),
            self::shorten(implode(',', $firstRow), 120)
        );
    }

    /**
     * 1行分を検証してDB登録用の値を返します。エラーがあれば null。
     *
     * @param list<string>       $fields
     * @param array<string, int> $columns
     * @param list<string>       $errors
     * @return list<string>|null [会員番号, 所属協会キー, 会社キー, 姓, 名]
     */
    private function parseRow(array $fields, array $columns, array &$errors): ?array
    {
        $value = static fn (string $field): string => isset($columns[$field]) ? ($fields[$columns[$field]] ?? '') : '';

        $memberNo = Text::normalize($value('member_no'));
        if ($memberNo === '') {
            $errors[] = '会員番号が空です';
        }

        $kyokai = $this->masters->resolveKyokai($value('kyokai'));
        if (Text::normalize($value('kyokai')) === '') {
            $errors[] = '所属協会が空です';
        } elseif ($kyokai === null) {
            $errors[] = '所属協会「' . self::shorten($value('kyokai')) . '」がマスタにありません';
        }

        if (isset($columns['name'])) {
            $parts = explode(' ', Text::normalize($value('name')), 2);
            $sei = $parts[0];
            $mei = $parts[1] ?? '';
            if ($sei === '') {
                $errors[] = '氏名が空です';
            } elseif ($mei === '') {
                $errors[] = '氏名「' . self::shorten($value('name')) . '」を姓と名に分けられません（姓と名の間に空白が必要です）';
            }
        } else {
            $sei = Text::normalize($value('sei'));
            $mei = Text::normalize($value('mei'));
            if ($sei === '') {
                $errors[] = '姓が空です';
            }
            if ($mei === '') {
                $errors[] = '名が空です';
            }
        }

        $company = $this->masters->resolveCompany($value('company'));
        if (Text::normalize($value('company')) === '') {
            $errors[] = '会社名が空です';
        } elseif ($company === null) {
            $errors[] = '会社名「' . self::shorten($value('company')) . '」がマスタにありません';
        }

        if ($errors !== []) {
            return null;
        }

        return [$memberNo, (string) $kyokai, (string) $company, $sei, $mei];
    }

    private function collectWarnings(PDO $pdo, ImportReport $report): void
    {
        $duplicates = $pdo->query(
            "SELECT kyokai, company, sei, mei, GROUP_CONCAT(row_no, '・') AS row_nos
               FROM members
              GROUP BY sei, mei, kyokai, company
             HAVING COUNT(DISTINCT member_no) > 1
              ORDER BY MIN(row_no)"
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($duplicates !== []) {
            $report->addWarning(
                sprintf('所属協会・氏名・会社名がすべて同じで、会員番号が異なる会員が %s 組あります。これらの会員は検索すると「会員番号を特定できない」旨が表示されます。', number_format(count($duplicates))),
                array_map(
                    fn (array $row): string => sprintf(
                        '%s / %s %s / %s（%s行目）',
                        $this->masters->kyokaiLabel($row['kyokai']),
                        $row['sei'],
                        $row['mei'],
                        $this->masters->companyLabel($row['company']),
                        $row['row_nos']
                    ),
                    array_slice($duplicates, 0, self::WARNING_ITEMS)
                )
            );
        }

        $sameNumbers = $pdo->query(
            "SELECT member_no, GROUP_CONCAT(row_no, '・') AS row_nos
               FROM members
              GROUP BY member_no
             HAVING COUNT(*) > 1
              ORDER BY MIN(row_no)"
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($sameNumbers !== []) {
            $report->addWarning(
                sprintf('同じ会員番号が複数の行にあります（%s件）。', number_format(count($sameNumbers))),
                array_map(
                    static fn (array $row): string => sprintf('会員番号 %s（%s行目）', $row['member_no'], $row['row_nos']),
                    array_slice($sameNumbers, 0, self::WARNING_ITEMS)
                )
            );
        }

        [$minLength, $maxLength, $nonNumeric] = $pdo->query(
            "SELECT MIN(LENGTH(member_no)), MAX(LENGTH(member_no)), SUM(member_no GLOB '*[^0-9]*') FROM members"
        )->fetch(PDO::FETCH_NUM);
        if ((int) $nonNumeric === 0 && (int) $minLength !== (int) $maxLength) {
            $report->addWarning(sprintf(
                '会員番号の桁数がそろっていません（%d〜%d桁）。Excelで開いて保存した際に先頭の「0」が消えていないか確認してください。',
                $minLength,
                $maxLength
            ));
        }
    }

    private function prepareDataDir(): void
    {
        if (!is_dir($this->dataDir) && !@mkdir($this->dataDir, 0775, true) && !is_dir($this->dataDir)) {
            throw new ImportException('データ保存先のフォルダを作成できません: ' . $this->dataDir);
        }
        if (!is_writable($this->dataDir)) {
            throw new ImportException('データ保存先のフォルダに書き込めません。書き込み権限を確認してください: ' . $this->dataDir);
        }
        // 異常終了で残った一時ファイルを片付ける
        foreach (glob($this->targetPath() . '.tmp-*') ?: [] as $stale) {
            if (filemtime($stale) < time() - 3600) {
                @unlink($stale);
            }
        }
    }

    /** @return resource */
    private function lock()
    {
        $handle = fopen($this->dataDir . '/import.lock', 'c');
        if ($handle === false) {
            throw new ImportException('データ保存先のフォルダに書き込めません: ' . $this->dataDir);
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new ImportException('別の取込処理が実行中です。しばらくしてからもう一度お試しください。');
        }

        return $handle;
    }

    private function writeLog(ImportReport $report, string $operator): void
    {
        $entry = [
            'at' => $report->finishedAt,
            'status' => $report->status,
            'operator' => $operator,
            'source_name' => $report->sourceName,
            'source_sha256' => $report->sha256,
            'imported_rows' => $report->importedRows,
            'error_rows' => $report->errorRows,
            'previous_count' => $report->previousCount,
            'failure' => $report->failure,
        ];
        $dir = dirname($this->logPath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        if (@file_put_contents($this->logPath(), $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('[qryno] could not write import log: ' . $this->logPath());
        }
    }

    /** 見出しの照合用キー（全角/半角・空白・大小文字の違いを無視） */
    private static function headerKey(string $name): string
    {
        return str_replace(' ', '', Text::normalize($name));
    }

    /** @param list<string> $fields */
    private static function isBlank(array $fields): bool
    {
        foreach ($fields as $field) {
            if (Text::normalize($field) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function shorten(string $value, int $width = 40): string
    {
        return mb_strimwidth(trim($value), 0, $width, '…', 'UTF-8');
    }
}
