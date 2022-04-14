<?php

declare(strict_types=1);

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
        if (!array_key_exists('created_at', $dropletData)) {
            $dropletData['created_at'] = (new \DateTime())->format(Instance::CREATED_AT_FORMAT);
        }

        return new Instance(
            new Droplet(
                DropletDataFactory::normalize($dropletData)
            )
        );
    }
}
