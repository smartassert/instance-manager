<?php

namespace App\Services;

use App\Model\InstanceCollection;
use Psr\Http\Client\ClientExceptionInterface;

class InstanceCollectionHydrator
{
    public function __construct(
        private InstanceClient $instanceClient,
    ) {
    }

    public function hydrate(InstanceCollection $collection, string $stateUrl): InstanceCollection
    {
        $hydratedInstances = [];

        foreach ($collection as $instance) {
            try {
                $hydratedInstances[] = $instance->withAdditionalState(
                    $this->instanceClient->getState($instance, $stateUrl)
                );
            } catch (ClientExceptionInterface) {
                // Intentionally ignore HTTP exceptions
            }
        }

        return new InstanceCollection($hydratedInstances);
    }
}
