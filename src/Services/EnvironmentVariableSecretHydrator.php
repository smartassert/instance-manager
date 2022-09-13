<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableCollection;
use App\Model\Secret;
use App\Model\SecretCollection;
use App\Model\SecretPlaceholder;

class EnvironmentVariableSecretHydrator
{
    public function hydrateCollection(
        EnvironmentVariableCollection $environmentVariables,
        SecretCollection $secrets
    ): EnvironmentVariableCollection {
        foreach ($environmentVariables as $index => $environmentVariable) {
            $environmentVariables = $environmentVariables->set($index, $this->hydrate($environmentVariable, $secrets));
        }

        return $environmentVariables;
    }

    public function hydrate(EnvironmentVariable $environmentVariable, SecretCollection $secrets): EnvironmentVariable
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

    private function hydrateValue(string $value, SecretCollection $secrets): string
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

    private function getSecretByKey(string $key, SecretCollection $secrets): ?Secret
    {
        foreach ($secrets as $secret) {
            if ($key === $secret->getKey()) {
                return $secret;
            }
        }

        return null;
    }
}
