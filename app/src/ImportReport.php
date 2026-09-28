<?php

declare(strict_types=1);

namespace Qryno;

/** CSV取込の結果 */
final class ImportReport
{
    /** 取込完了（会員データを置き換えた） */
    public const APPLIED = 'applied';
    /** 検証のみ（会員データは置き換えていない） */
    public const VALIDATED = 'validated';
    /** 中止（会員データは置き換えていない） */
    public const FAILED = 'failed';

    public string $status = self::FAILED;
    public ?string $failure = null;
    /** 件数の大幅な減少により置き換えを止めた */
    public bool $shrinkBlocked = false;

    public string $sourceName = '';
    public int $fileSize = 0;
    public string $sha256 = '';
    public string $encoding = '';
    public string $delimiter = '';
    public int $headerRow = 0;
    /** @var array<string, string> 項目名 => CSVの見出し */
    public array $columns = [];

    /** 空行を除いたデータ行数 */
    public int $dataRows = 0;
    public int $importedRows = 0;
    public int $errorRows = 0;
    /** @var list<array{row: int, message: string}> 先頭から上限件数まで */
    public array $errors = [];
    /** @var list<array{message: string, items: list<string>}> */
    public array $warnings = [];

    public int $previousCount = 0;
    public float $seconds = 0.0;
    public string $finishedAt = '';

    public function addError(int $row, string $message, int $limit): void
    {
        $this->errorRows++;
        if (count($this->errors) < $limit) {
            $this->errors[] = ['row' => $row, 'message' => $message];
        }
    }

    /** @param list<string> $items */
    public function addWarning(string $message, array $items = []): void
    {
        $this->warnings[] = ['message' => $message, 'items' => $items];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $report = new self();
        foreach ($data as $key => $value) {
            if (property_exists($report, $key)) {
                $report->{$key} = $value;
            }
        }

        return $report;
    }
}
