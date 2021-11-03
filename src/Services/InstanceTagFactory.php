<?php

namespace App\Services;

class InstanceTagFactory
{
    public function create(string $collectionTag, string $imageId): string
    {
        return $collectionTag . '-' . $imageId;
    }
}
