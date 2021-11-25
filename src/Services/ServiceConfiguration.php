<?php

namespace App\Services;

use App\Model\EnvironmentVariableList;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;

class ServiceConfiguration
{
    public const ENV_VAR_FILENAME = 'env.json';
    public const CONFIGURATION_FILENAME = 'configuration.json';
    public const IMAGE_FILENAME = 'image.json';

    public function __construct(
        private string $configurationDirectory,
    ) {
    }

    public function exists(string $serviceId): bool
    {
        return $this->jsonFileExists($serviceId, self::CONFIGURATION_FILENAME);
    }

    public function getEnvironmentVariables(string $serviceId): EnvironmentVariableList
    {
        $environmentVariables = [];

        $data = $this->readJsonFileToArray($serviceId, self::ENV_VAR_FILENAME);
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $environmentVariables[] = $key . '=' . $value;
                }
            }
        }

        return new EnvironmentVariableList($environmentVariables);
    }

    public function getImageId(string $serviceId): ?string
    {
        $data = $this->readJsonFileToArray($serviceId, self::IMAGE_FILENAME);
        $imageId = $data['image_id'] ?? null;

        return is_string($imageId) ? $imageId : null;
    }

    public function getHealthCheckUrl(string $serviceId): ?string
    {
        $configuration = $this->getServiceConfiguration($serviceId);

        return $configuration instanceof ServiceConfigurationModel
            ? $configuration->getHealthCheckUrl()
            : null;
    }

    public function getStateUrl(string $serviceId): ?string
    {
        $configuration = $this->getServiceConfiguration($serviceId);

        return $configuration instanceof ServiceConfigurationModel
            ? $configuration->getStateUrl()
            : null;
    }

    public function getServiceConfiguration(string $serviceId): ?ServiceConfigurationModel
    {
        $data = $this->readJsonFileToArray($serviceId, self::CONFIGURATION_FILENAME);
        if (null === $data) {
            return null;
        }

        return ServiceConfigurationModel::create($serviceId, $data);
    }

    private function jsonFileExists(string $serviceId, string $filename): bool
    {
        $filePath = $this->configurationDirectory . '/' . $serviceId . '/' . $filename;

        return file_exists($filePath) && is_readable($filePath);
    }

    /**
     * @return array<mixed>
     */
    private function readJsonFileToArray(string $serviceId, string $filename): ?array
    {
        if (false === $this->jsonFileExists($serviceId, $filename)) {
            return null;
        }

        $filePath = $this->configurationDirectory . '/' . $serviceId . '/' . $filename;

        $content = (string) file_get_contents($filePath);
        $data = json_decode($content, true);

        if (false === is_array($data)) {
            return [];
        }

        return $data;
    }
}
