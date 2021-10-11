<?php

namespace App\Tests\Services;

use App\Model\Instance;
use DigitalOceanV2\Entity\Droplet;

class InstanceFactory
{
    /**
     * @param array<mixed> $dropletData
     */
    public static function create(array $dropletData): Instance
    {
        return new Instance(
            new Droplet(
                DropletDataFactory::normalize($dropletData)
            )
        );
    }
}
