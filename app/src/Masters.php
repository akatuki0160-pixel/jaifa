<?php

declare(strict_types=1);

namespace Qryno;

use InvalidArgumentException;

/**
 * 所属協会・会社名のマスタ（プルダウンの選択肢）
 *
 * 所属協会は「130　東京」のコード部分（130）を、会社名は正規化した名称を
 * データベース上のキーとして使います。
 */
final class Masters
{
    /** @var list<array{value: string, key: string}> */
    private array $kyokaiOptions = [];
    /** @var array<string, string> 正規化した表記 => キー */
    private array $kyokaiIndex = [];
    /** @var list<array{value: string, key: string}> */
    private array $companyOptions = [];
    /** @var array<string, string> 正規化した表記 => キー */
    private array $companyIndex = [];

    /**
     * @param list<string> $kyokai  「コード　名称」形式の一覧
     * @param list<string> $companies 会社名の一覧
     */
    public function __construct(array $kyokai, array $companies)
    {
        foreach ($kyokai as $label) {
            $label = (string) $label;
            $normalized = Text::normalize($label);
            if ($normalized === '') {
                continue;
            }
            $aliases = [$normalized];
            if (preg_match('/^(\d+) (.+)$/u', $normalized, $m) === 1) {
                $key = $m[1];
                // 「130」「東京」だけの表記や、Excelで先頭の0が消えた「10」でも引けるようにする
                array_push($aliases, $m[1], ltrim($m[1], '0'), $m[2]);
            } else {
                $key = $normalized;
            }
            if (in_array($key, array_column($this->kyokaiOptions, 'key'), true)) {
                throw new InvalidArgumentException("所属協会マスタの「{$label}」のコードが他の項目と重複しています。");
            }
            foreach (array_unique($aliases) as $alias) {
                if ($alias === '') {
                    continue;
                }
                if (isset($this->kyokaiIndex[$alias]) && $this->kyokaiIndex[$alias] !== $key) {
                    throw new InvalidArgumentException("所属協会マスタの「{$label}」が他の項目と重複しています。");
                }
                $this->kyokaiIndex[$alias] = $key;
            }
            $this->kyokaiOptions[] = ['value' => $label, 'key' => $key];
        }

        foreach ($companies as $label) {
            $label = (string) $label;
            $key = Text::normalize($label);
            if ($key === '') {
                continue;
            }
            if (isset($this->companyIndex[$key])) {
                throw new InvalidArgumentException("会社名マスタの「{$label}」が重複しています。");
            }
            $this->companyIndex[$key] = $key;
            $this->companyOptions[] = ['value' => $label, 'key' => $key];
        }
    }

    public static function fromFile(string $file): self
    {
        $masters = require $file;

        return new self($masters['kyokai'] ?? [], $masters['company'] ?? []);
    }

    /** @return list<string> プルダウンの値（表示順） */
    public function kyokaiOptions(): array
    {
        return array_column($this->kyokaiOptions, 'value');
    }

    /** @return list<string> プルダウンの値（表示順） */
    public function companyOptions(): array
    {
        return array_column($this->companyOptions, 'value');
    }

    /** キーから表示名（「130　東京」など）を返します */
    public function kyokaiLabel(string $key): string
    {
        foreach ($this->kyokaiOptions as $option) {
            if ($option['key'] === $key) {
                return $option['value'];
            }
        }

        return $key;
    }

    /** キーから表示名を返します */
    public function companyLabel(string $key): string
    {
        foreach ($this->companyOptions as $option) {
            if ($option['key'] === $key) {
                return $option['value'];
            }
        }

        return $key;
    }

    /** 所属協会の表記（「130　東京」「130」「東京」など）からキーを返します。該当なしは null */
    public function resolveKyokai(string $value): ?string
    {
        return $this->kyokaiIndex[Text::normalize($value)] ?? null;
    }

    /** 会社名からキーを返します。該当なしは null */
    public function resolveCompany(string $value): ?string
    {
        return $this->companyIndex[Text::normalize($value)] ?? null;
    }

    /** 取込済みデータとマスタの整合確認用。キーの集合が変わったときだけ値が変わります。 */
    public function fingerprint(): string
    {
        $kyokai = array_column($this->kyokaiOptions, 'key');
        $companies = array_column($this->companyOptions, 'key');
        sort($kyokai, SORT_STRING);
        sort($companies, SORT_STRING);

        return hash('sha256', json_encode([$kyokai, $companies], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
