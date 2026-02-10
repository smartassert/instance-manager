<?php

declare(strict_types=1);

namespace App\Services;

use SmartAssert\DigitalOceanDropletConfiguration\Configuration as DropletConfiguration;
use SmartAssert\DigitalOceanDropletConfiguration\Factory as DropletConfigurationFactory;

class InstanceConfigurationFactory
{
    public function __construct(
        private DropletConfigurationFactory $dropletConfigurationFactory,
    ) {}

    /**
     * @param string[] $tags
     */
    public function create(string $firstBootScript, array $tags): DropletConfiguration
    {
        return $this->dropletConfigurationFactory
            ->create([
                DropletConfigurationFactory::KEY_TAGS => $tags,
            ])
            ->withUserData($firstBootScript)
        ;
    }
}
