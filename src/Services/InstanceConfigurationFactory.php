<?php

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
    public function create(string $postCreateScript, array $tags): DropletConfiguration
    {
        $postCreateScript = '' !== $postCreateScript ? $postCreateScript : '# No post-create script';

        $configuration = $this->dropletConfigurationFactory->create([
            DropletConfigurationFactory::KEY_TAGS => $tags,
        ]);

        if ('' !== $configuration->getUserData()) {
            $configuration = $configuration->appendUserData("\n\n");
        }

        return $configuration->appendUserData(
            '# Post-create script' . "\n" . $postCreateScript
        );
    }
}
