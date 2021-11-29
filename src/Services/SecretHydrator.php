<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\KeyValue;
use App\Model\SecretPlaceholderContainerInterface;
use App\Model\SecretPlaceholderInterface;
use Doctrine\Common\Collections\Collection;

class SecretHydrator
{
    /**
     * @param Collection<int, KeyValue> $secrets
     */
    public function hydrate(
        SecretPlaceholderContainerInterface $placeholderContainer,
        Collection $secrets
    ): SecretPlaceholderContainerInterface {
        $secretPlaceholder = $placeholderContainer->getSecretPlaceholder();

        if ($secretPlaceholder instanceof SecretPlaceholderInterface) {
            $secretName = $secretPlaceholder->getSecretName();
            $secret = $this->getSecretByKey($secretName, $secrets);
            $secretValue = $secret?->getValue();

            if (is_string($secretValue)) {
                $placeholderContainer = $placeholderContainer->replace($secretPlaceholder, $secretValue);
            }
        }

        return $placeholderContainer;
    }

    /**
     * @param Collection<int, KeyValue> $secrets
     */
    private function getSecretByKey(string $key, Collection $secrets): ?KeyValue
    {
        foreach ($secrets as $secret) {
            if ($key === $secret->getKey()) {
                return $secret;
            }
        }

        return null;
    }
}
