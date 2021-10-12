<?php

namespace App\Services;

use App\Model\InstanceCollection;
use Psr\Http\Client\ClientExceptionInterface;

class InstanceCollectionHydrator
{
    public function __construct(
        private InstanceHydrator $instanceHydrator,
    ) {
    }

    public function hydrate(InstanceCollection $collection): InstanceCollection
    {
        $hydratedInstances = [];

        foreach ($collection as $instance) {
            try {
                $hydratedInstances[] = $this->instanceHydrator->hydrate($instance);
            } catch (ClientExceptionInterface) {
                // Intentionally ignore HTTP exceptions
            }
        }

        return new InstanceCollection($hydratedInstances);
    }
}
