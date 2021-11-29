<?php

declare(strict_types=1);

namespace App\Services;

class InstanceTagFactory
{
    public function create(string $serviceId, int $imageId): string
    {
        return $serviceId . '-' . $imageId;
    }
}
