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

            $secret = $secrets->get($secretName);
            if (is_string($secret)) {
                $placeholderContainer = $placeholderContainer->replace($secretPlaceholder, $secret);
            }
        }

        return $placeholderContainer;
    }
}
