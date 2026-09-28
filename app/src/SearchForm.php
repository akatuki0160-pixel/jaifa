<?php

declare(strict_types=1);

namespace Qryno;

/** 検索画面の入力値と検証結果 */
final class SearchForm
{
    public const FIELDS = ['kyokai', 'sei', 'mei', 'company'];

    /**
     * @param array<string, string> $values 入力されたままの値（再表示用）
     * @param array<string, bool>   $errors 未入力・不正な項目
     * @param array<string, string> $keys   検索用に正規化した値（エラーがなければ4項目そろう）
     */
    private function __construct(
        public readonly array $values,
        public readonly array $errors,
        public readonly array $keys,
    ) {
    }

    public static function empty(): self
    {
        return new self(array_fill_keys(self::FIELDS, ''), [], []);
    }

    /**
     * 入力値だけを保持します（検証しない）。
     *
     * @param array<string, string> $input
     */
    public static function fromInput(array $input): self
    {
        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = (string) ($input[$field] ?? '');
        }

        return new self($values, [], []);
    }

    /** @param array<string, string> $input */
    public static function validate(array $input, Masters $masters, int $maxNameLength): self
    {
        $values = self::fromInput($input)->values;
        $keys = [];
        $errors = [];

        $kyokai = $masters->resolveKyokai($values['kyokai']);
        if ($kyokai === null) {
            $errors['kyokai'] = true;
        } else {
            $keys['kyokai'] = $kyokai;
        }

        foreach (['sei', 'mei'] as $field) {
            $name = Text::normalize($values[$field]);
            if ($name === '' || mb_strlen($name) > $maxNameLength) {
                $errors[$field] = true;
            } else {
                $keys[$field] = $name;
            }
        }

        $company = $masters->resolveCompany($values['company']);
        if ($company === null) {
            $errors['company'] = true;
        } else {
            $keys['company'] = $company;
        }

        return new self($values, $errors, $errors === [] ? $keys : []);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }
}
