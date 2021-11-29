<?php

declare(strict_types=1);

namespace App\Services;

use SmartAssert\DigitalOceanDropletConfiguration\Configuration as DropletConfiguration;
use SmartAssert\DigitalOceanDropletConfiguration\Factory as DropletConfigurationFactory;

class InstanceConfigurationFactory
{
    public function __construct(
        private DropletConfigurationFactory $dropletConfigurationFactory,
    ) {
    }

    /**
     * @param string[] $tags
     */
    public function create(string $firstBootScript, array $tags): DropletConfiguration
    {
        $firstBootScript = '' !== $firstBootScript ? $firstBootScript : '# No first-boot script';

        $configuration = $this->dropletConfigurationFactory->create([
            DropletConfigurationFactory::KEY_TAGS => $tags,
        ]);

        if ('' !== $configuration->getUserData()) {
            $configuration = $configuration->appendUserData("\n\n");
        }

        return $configuration->appendUserData(
            '# First-boot script' . "\n" . $firstBootScript
        );
    }
}
