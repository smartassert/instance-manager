<?php

namespace App\Services;

class InstanceTagFactory
{
    public function create(string $serviceId, string $imageId): string
    {
        return $serviceId . '-' . $imageId;
    }
}
