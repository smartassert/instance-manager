<?php

namespace App\Services;

use App\Model\EnvironmentVariable;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

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

    /**
     * @return Collection<int, EnvironmentVariable>
     */
    public function getEnvironmentVariables(string $serviceId): Collection
    {
        $collection = new ArrayCollection();

        $data = $this->readJsonFileToArray($serviceId, self::ENV_VAR_FILENAME);
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $collection[] = new EnvironmentVariable($key, $value);
                }
            }
        }

        return $collection;
    }

    public function getImageId(string $serviceId): ?int
    {
        $data = $this->readJsonFileToArray($serviceId, self::IMAGE_FILENAME);
        $imageId = $data['image_id'] ?? null;

        return is_int($imageId) || is_numeric($imageId) ? (int) $imageId : null;
    }

    public function getServiceConfiguration(string $serviceId): ?ServiceConfigurationModel
    {
        $data = $this->readJsonFileToArray($serviceId, self::CONFIGURATION_FILENAME);
        if (null === $data) {
            return null;
        }

        return ServiceConfigurationModel::create($serviceId, $data);
    }

    public function setServiceConfiguration(ServiceConfigurationModel $serviceConfiguration): bool
    {
        $data = [];

        $healthCheckUrl = $serviceConfiguration->getHealthCheckUrl();
        if (is_string($healthCheckUrl)) {
            $data[ServiceConfigurationModel::KEY_HEALTH_CHECK_URL] = $healthCheckUrl;
        }

        $stateUrl = $serviceConfiguration->getStateUrl();
        if (is_string($stateUrl)) {
            $data[ServiceConfigurationModel::KEY_STATE_URL] = $stateUrl;
        }

        $serviceConfigurationDirectory = $this->getServiceConfigurationDirectory($serviceConfiguration->getServiceId());
        if (!file_exists($serviceConfigurationDirectory)) {
            $makeDirectoryResult = mkdir(directory: $serviceConfigurationDirectory, recursive: true);
            if (false === $makeDirectoryResult) {
                return false;
            }
        }

        $filePath = $this->getFilePath($serviceConfiguration->getServiceId(), self::CONFIGURATION_FILENAME);
        $writeResult = file_put_contents(
            $filePath,
            (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return is_int($writeResult);
    }

    private function jsonFileExists(string $serviceId, string $filename): bool
    {
        $filePath = $this->getFilePath($serviceId, $filename);

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

        $filePath = $this->getFilePath($serviceId, $filename);

        $content = (string) file_get_contents($filePath);
        $data = json_decode($content, true);

        if (false === is_array($data)) {
            return [];
        }

        return $data;
    }

    private function getServiceConfigurationDirectory(string $serviceId): string
    {
        return $this->configurationDirectory . '/' . $serviceId;
    }

    private function getFilePath(string $serviceId, string $filename): string
    {
        return $this->getServiceConfigurationDirectory($serviceId) . '/' . $filename;
    }
}
