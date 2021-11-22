<?php

namespace App\Services;

use App\Model\EnvironmentVariableList;

class ServiceConfiguration
{
    public const ENV_VAR_FILENAME = 'env.json';

    public function __construct(
        private string $configurationDirectory,
    ) {
    }

    public function getEnvironmentVariables(string $serviceId): EnvironmentVariableList
    {
        $filePath = $this->configurationDirectory . '/' . $serviceId . '/env.json';
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
}
