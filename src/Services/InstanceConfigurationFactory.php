<?php

namespace App\Services;

use SmartAssert\DigitalOceanDropletConfiguration\Configuration as DropletConfiguration;
use SmartAssert\DigitalOceanDropletConfiguration\Factory;

class InstanceConfigurationFactory
{
    public function __construct(
        private Factory $dropletConfigurationFactory,
    ) {
    }

    public function create(string $postCreateScript): DropletConfiguration
    {
        $postCreateScript = '' !== $postCreateScript ? $postCreateScript : '# No post-create script';

        $configuration = $this->dropletConfigurationFactory->create();
        if ('' !== $configuration->getUserData()) {
            $configuration = $configuration->appendUserData("\n\n");
        }

        return $configuration->appendUserData(
            '# Post-create script' . "\n" . $postCreateScript
        );
    }
}
