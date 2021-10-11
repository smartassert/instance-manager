<?php

namespace App\Model;

class Filter implements FilterInterface
{
    /**
     * @param string                        $field
     * @param bool|float|int|string         $value
     * @param FilterInterface::MATCH_TYPE_* $matchType
     */
    public function __construct(
        private string $field,
        private bool|int|string|float $value,
        private string $matchType,
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue(): bool|int|string|float
    {
        return $this->value;
    }

    /**
     * @return FilterInterface::MATCH_TYPE_*
     */
    public function getMatchType(): string
    {
        return $this->matchType;
    }
}
