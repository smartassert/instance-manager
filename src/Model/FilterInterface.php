<?php

declare(strict_types=1);

namespace App\Model;

interface FilterInterface
{
    public const MATCH_TYPE_POSITIVE = 'positive';
    public const MATCH_TYPE_NEGATIVE = 'negative';

    public function getField(): string;

    public function getValue(): bool|int|string|float;

    /**
     * @return self::MATCH_TYPE_*
     */
    public function getMatchType(): string;
}
