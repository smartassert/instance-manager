<?php

namespace App\Services;

use App\Model\KeyValueCollection;
use App\Model\SecretPlaceholderContainerInterface;
use App\Model\SecretPlaceholderInterface;

class SecretHydrator
{
    public function hydrate(
        SecretPlaceholderContainerInterface $placeholderContainer,
        KeyValueCollection $secrets
    ): SecretPlaceholderContainerInterface {
        $secretPlaceholder = $placeholderContainer->getSecretPlaceholder();

        if ($secretPlaceholder instanceof SecretPlaceholderInterface) {
            $secretName = $secretPlaceholder->getSecretName();
            $secret = $secrets->getByKey($secretName);
            $secretValue = $secret?->getValue();

            if (is_string($secretValue)) {
                $placeholderContainer = $placeholderContainer->replace($secretPlaceholder, $secretValue);
            }
        }

        return $placeholderContainer;
    }
}
