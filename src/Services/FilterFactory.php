<?php

namespace App\Services;

use App\Model\Filter;
use App\Model\FilterInterface;

class FilterFactory
{
    /**
     * @param FilterInterface::MATCH_TYPE_* $matchType
     *
     * @return Filter[]
     */
    public function createFromString(string $filter, string $matchType): array
    {
        $filterCollectionData = json_decode($filter, true);
        if (!is_array($filterCollectionData)) {
            return [];
        }

        $filters = [];
        foreach ($filterCollectionData as $filterData) {
            $fieldName = key($filterData);
            $value = $filterData[$fieldName];

            $fieldNameValid = is_string($fieldName);
            $valueValid = is_scalar($value);

            if ($fieldNameValid && $valueValid) {
                $filters[] = new Filter($fieldName, $value, $matchType);
            }
        }

        return $filters;
    }
}
