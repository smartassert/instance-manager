<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Secret;
use App\Model\SecretCollection;

class SecretFactory
{
    public function create(string $json): SecretCollection
    {
        $itemsData = json_decode($json, true);
        $collection = [];

        if (is_array($itemsData)) {
            foreach ($itemsData as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $collection[] = new Secret($key, $value);
                }
            }
        }

        return new SecretCollection($collection);
    }
}
