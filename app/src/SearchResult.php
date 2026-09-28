<?php

declare(strict_types=1);

namespace Qryno;

/** 会員番号の検索結果 */
final class SearchResult
{
    public const FOUND = 'found';
    public const NOT_FOUND = 'not_found';
    /** 同じ条件の会員が複数いて会員番号を特定できない */
    public const MULTIPLE = 'multiple';

    private function __construct(
        public readonly string $status,
        public readonly ?string $memberNo = null,
    ) {
    }

    public static function found(string $memberNo): self
    {
        return new self(self::FOUND, $memberNo);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND);
    }

    public static function multiple(): self
    {
        return new self(self::MULTIPLE);
    }

    public function isFound(): bool
    {
        return $this->status === self::FOUND;
    }
}
