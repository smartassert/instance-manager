<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\InstanceCollection;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;
use Psr\Http\Client\ClientExceptionInterface;

class InstanceCollectionHydrator
{
    public function __construct(
        private InstanceClient $instanceClient,
    ) {
    }

    public function hydrate(
        ServiceConfigurationModel $serviceConfigurationModel,
        InstanceCollection $collection
    ): InstanceCollection {
        if ('' === $serviceConfigurationModel->getStateUrl()) {
            return $collection;
        }

        $hydratedInstances = [];

        foreach ($collection as $instance) {
            try {
                $hydratedInstances[] = $instance->withAdditionalState(
                    $this->instanceClient->getState($serviceConfigurationModel, $instance)
                );
            } catch (ClientExceptionInterface) {
                // Intentionally ignore HTTP exceptions
            }
        }

        return new InstanceCollection($hydratedInstances);
    }
}
