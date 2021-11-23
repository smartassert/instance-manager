<?php

namespace App\Services;

use App\Model\KeyValueCollection;
use App\Model\SecretPlaceholderContainerCollectionInterface;

class SecretCollectionHydrator
{
    public function __construct(
        private SecretHydrator $secretHydrator,
    ) {
    }

    public function hydrate(
        SecretPlaceholderContainerCollectionInterface $placeholderContainers,
        KeyValueCollection $secrets
    ): SecretPlaceholderContainerCollectionInterface {
        foreach ($placeholderContainers as $placeholderContainer) {
            $mutatedPlaceholderContainer = $this->secretHydrator->hydrate($placeholderContainer, $secrets);

            if (false === $mutatedPlaceholderContainer->equals($placeholderContainer)) {
                $placeholderContainers = $placeholderContainers->set($mutatedPlaceholderContainer);
            }
        }

        return $placeholderContainers;
    }
}
