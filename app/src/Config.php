<?php

declare(strict_types=1);

namespace Qryno;

/**
 * 設定値。config/config.php → config/config.local.php → 環境変数 QRYNO_CONFIG のファイル の順に上書きします。
 */
final class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values)
    {
    }

    public static function load(string $root): self
    {
        $values = require $root . '/config/config.php';
        $overrides = [$root . '/config/config.local.php', getenv('QRYNO_CONFIG') ?: ''];
        foreach ($overrides as $file) {
            if ($file !== '' && is_file($file)) {
                $values = self::merge($values, require $file);
            }
        }

        return new self($values);
    }

    /** ドット区切りのキーで値を取り出します（例: 'site.title'） */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * 連想配列は再帰的に上書きし、リスト（電話番号など）は丸ごと置き換えます。
     *
     * @param array<mixed> $base
     * @param array<mixed> $override
     * @return array<mixed>
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])
                && !array_is_list($value) && !array_is_list($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
