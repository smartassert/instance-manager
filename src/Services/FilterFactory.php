<?php

namespace App\Services;

use App\Model\Filter;
use App\Model\FilterInterface;

class FilterFactory
{
    /**
     * @return Filter[]
     */
    public function createPositiveFiltersFromString(string $filter): array
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
                $filters[] = new Filter($fieldName, $value, FilterInterface::MATCH_TYPE_POSITIVE);
            }
        }

        return $filters;
    }

    /**
     * @return Filter[]
     */
    public function createNegativeFiltersFromString(string $filter): array
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
                $filters[] = new Filter($fieldName, $value, FilterInterface::MATCH_TYPE_NEGATIVE);
            }
        }

        return $filters;
    }
}
