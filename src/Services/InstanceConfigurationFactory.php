<?php

namespace App\Services;

use SmartAssert\DigitalOceanDropletConfiguration\Configuration as DropletConfiguration;
use SmartAssert\DigitalOceanDropletConfiguration\Factory as DropletConfigurationFactory;

class InstanceConfigurationFactory
{
    public function __construct(
        private DropletConfigurationFactory $dropletConfigurationFactory,
        private string $instanceCollectionTag,
        private string $instanceTag,
    ) {
        $this->instanceCollectionTag = trim($this->instanceCollectionTag);
        $this->instanceTag = trim($this->instanceTag);
    }

    public function create(string $postCreateScript): DropletConfiguration
    {
        $postCreateScript = '' !== $postCreateScript ? $postCreateScript : '# No post-create script';

        $configuration = $this->dropletConfigurationFactory->create([
            DropletConfigurationFactory::KEY_TAGS => [
                $this->instanceCollectionTag,
                $this->instanceTag,
            ],
        ]);

        if ('' !== $configuration->getUserData()) {
            $configuration = $configuration->appendUserData("\n\n");
        }

        return $configuration->appendUserData(
            '# Post-create script' . "\n" . $postCreateScript
        );
    }
}
