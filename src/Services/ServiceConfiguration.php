<?php

namespace App\Services;

use App\Model\EnvironmentVariableList;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;

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
        $data = $this->readJsonFileToArray($serviceId, self::ENV_VAR_FILENAME);

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
        return $this->createServiceConfiguration($serviceId)->getHealthCheckUrl();
    }

    public function getStateUrl(string $serviceId): ?string
    {
        return $this->createServiceConfiguration($serviceId)->getStateUrl();
    }

    private function createServiceConfiguration(string $serviceId): ServiceConfigurationModel
    {
        return ServiceConfigurationModel::create(
            $this->readJsonFileToArray($serviceId, self::CONFIGURATION_FILENAME)
        );
    }

    /**
     * @return array<mixed>
     */
    private function readJsonFileToArray(string $serviceId, string $filename): array
    {
        $filePath = $this->configurationDirectory . '/' . $serviceId . '/' . $filename;
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $content = (string) file_get_contents($filePath);
        $data = json_decode($content, true);

        if (false === is_array($data)) {
            return [];
        }

        return $data;
    }
}
