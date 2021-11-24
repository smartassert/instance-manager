<?php

namespace App\Services;

use App\Model\EnvironmentVariableList;

class ServiceConfiguration
{
    public const ENV_VAR_FILENAME = 'env.json';
    public const CONFIGURATION_FILENAME = 'configuration.json';

    public function __construct(
        private string $configurationDirectory,
    ) {
    }

    public function getEnvironmentVariables(string $serviceId): EnvironmentVariableList
    {
        $filePath = $this->configurationDirectory . '/' . $serviceId . '/' . self::ENV_VAR_FILENAME;
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return new EnvironmentVariableList([]);
        }

        $content = (string) file_get_contents($filePath);
        $data = json_decode($content, true);

        if (false === is_array($data)) {
            return new EnvironmentVariableList([]);
        }

        $environmentVariables = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $environmentVariables[] = $key . '=' . $value;
            }
        }

        return new EnvironmentVariableList($environmentVariables);
    }

    public function getHealthCheckUrl(string $serviceId): ?string
    {
        $filePath = $this->configurationDirectory . '/' . $serviceId . '/' . self::CONFIGURATION_FILENAME;
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        $content = (string) file_get_contents($filePath);
        $data = json_decode($content, true);

        if (false === is_array($data)) {
            return null;
        }

        $healthCheckUrl = $data['health_check_url'] ?? null;

        return is_string($healthCheckUrl) ? $healthCheckUrl : null;
    }
}
