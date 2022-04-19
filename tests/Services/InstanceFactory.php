<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Model\Instance;
use DigitalOceanV2\Entity\Droplet;

class InstanceFactory
{
    private const CREATED_AT_FORMAT = 'Y-m-d\TH:i:s.000\Z';

    /**
     * @param array<mixed> $dropletData
     */
    public static function create(array $dropletData): Instance
    {
        if (!array_key_exists('created_at', $dropletData)) {
            $dropletData['created_at'] = (new \DateTime())->format(self::CREATED_AT_FORMAT);
        }

        return new Instance(
            new Droplet(
                DropletDataFactory::normalize($dropletData)
            )
        );
    }
}
