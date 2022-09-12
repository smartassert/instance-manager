<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\EnvironmentVariable;
use App\Model\Secret;
use App\Model\SecretPlaceholder;
use Doctrine\Common\Collections\Collection;

class EnvironmentVariableSecretHydrator
{
    /**
     * @param Collection<int, EnvironmentVariable> $environmentVariables
     * @param Collection<int, Secret>            $secrets
     *
     * @return Collection<int, EnvironmentVariable>
     */
    public function hydrateCollection(Collection $environmentVariables, Collection $secrets): Collection
    {
        foreach ($environmentVariables as $index => $environmentVariable) {
            $environmentVariables->set($index, $this->hydrate($environmentVariable, $secrets));
        }

        return $environmentVariables;
    }

    /**
     * @param Collection<int, Secret> $secrets
     */
    public function hydrate(EnvironmentVariable $environmentVariable, Collection $secrets): EnvironmentVariable
    {
        $value = $environmentVariable->getValue();

        if (SecretPlaceholder::is($value)) {
            $mutatedEnvironmentVariable = new EnvironmentVariable(
                $environmentVariable->getKey(),
                $this->hydrateValue($value, $secrets)
            );

            if (false === $mutatedEnvironmentVariable->equals($environmentVariable)) {
                return $mutatedEnvironmentVariable;
            }
        }

        return $environmentVariable;
    }

    /**
     * @param Collection<int, Secret> $secrets
     */
    private function hydrateValue(string $value, Collection $secrets): string
    {
        if (false === SecretPlaceholder::is($value)) {
            return $value;
        }

        $secretPlaceholder = new SecretPlaceholder($value);
        $secretName = $secretPlaceholder->getSecretName();
        $secret = $this->getSecretByKey($secretName, $secrets);
        $secretValue = $secret?->getValue();

        return is_string($secretValue) ? $secretValue : (string) $secretPlaceholder;
    }

    /**
     * @param Collection<int, Secret> $secrets
     */
    private function getSecretByKey(string $key, Collection $secrets): ?Secret
    {
        foreach ($secrets as $secret) {
            if ($key === $secret->getKey()) {
                return $secret;
            }
        }

        return null;
    }
}
