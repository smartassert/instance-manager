<?php

namespace App\Services;

class InstanceTagFactory
{
    public function create(string $serviceId, int $imageId): string
    {
        return $serviceId . '-' . $imageId;
    }
}
