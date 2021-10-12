<?php

namespace App\Services;

use App\Model\Instance;
use Psr\Http\Client\ClientExceptionInterface;

class InstanceHydrator
{
    public function __construct(
        private InstanceClient $instanceClient,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function hydrate(Instance $instance): Instance
    {
        return $instance->withAdditionalState(
            $this->instanceClient->getState($instance)
        );
    }
}
