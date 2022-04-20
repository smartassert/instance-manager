<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\ImageIdMissingException;
use App\Model\Configuration;
use App\Model\EnvironmentVariable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ServiceConfiguration
{
    public const ENV_VAR_FILENAME = 'env.json';
    public const CONFIGURATION_FILENAME = 'configuration.json';
    public const IMAGE_FILENAME = 'image.json';
    public const DOMAIN_FILENAME = 'domain.json';

    /**
     * @var Collection<int, EnvironmentVariable>
     */
    private Collection $environmentVariables;
    private ?Configuration $serviceConfiguration = null;
    private ?Configuration $imageConfiguration = null;
    private ?Configuration $domainConfiguration = null;

    public function __construct(
        private readonly ConfigurationFactory $configurationFactory,
        private readonly string $configurationDirectory,
        private readonly string $defaultDomain,
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
        if (!isset($this->environmentVariables)) {
            $collection = new ArrayCollection();

            $configuration = $this->createConfiguration($serviceId, self::ENV_VAR_FILENAME);

            if ($configuration instanceof Configuration) {
                $data = $configuration->getAll();

                foreach ($data as $key => $value) {
                    if (is_string($key) && is_string($value)) {
                        $collection[] = new EnvironmentVariable($key, $value);
                    }
                }
            }

            $this->environmentVariables = $collection;
        }

        return $this->environmentVariables;
    }

    /**
     * @throws ImageIdMissingException
     */
    public function getImageId(string $serviceId): int
    {
        if (!$this->imageConfiguration instanceof Configuration) {
            $this->imageConfiguration = $this->createConfiguration($serviceId, self::IMAGE_FILENAME);
        }

        $imageId = $this->imageConfiguration?->getInt('image_id');

        if (!is_int($imageId)) {
            throw new ImageIdMissingException($serviceId);
        }

        return $imageId;
    }

    public function getDomain(string $serviceId): string
    {
        if (!$this->domainConfiguration instanceof Configuration) {
            $this->domainConfiguration = $this->createConfiguration($serviceId, self::DOMAIN_FILENAME);
        }

        $domain = $this->domainConfiguration instanceof Configuration
            ? $this->domainConfiguration->getString('domain')
            : null;

        return is_string($domain) ? $domain : $this->defaultDomain;
    }

    public function getHealthCheckUrl(string $serviceId): ?string
    {
        return $this->getServiceConfiguration($serviceId)?->getString('health_check_url');
    }

    public function getStateUrl(string $serviceId): ?string
    {
        return $this->getServiceConfiguration($serviceId)?->getString('state_url');
    }

    public function setServiceConfiguration(string $serviceId, string $healthCheckUrl, string $stateUrl): bool
    {
        $data = ['health_check_url' => $healthCheckUrl, 'state_url' => $stateUrl];

        $serviceConfigurationDirectory = $this->getServiceConfigurationDirectory($serviceId);
        if (!file_exists($serviceConfigurationDirectory)) {
            $makeDirectoryResult = mkdir(directory: $serviceConfigurationDirectory, recursive: true);
            if (false === $makeDirectoryResult) {
                return false;
            }
        }

        $filePath = $this->getFilePath($serviceId, self::CONFIGURATION_FILENAME);
        $writeResult = file_put_contents(
            $filePath,
            (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return is_int($writeResult);
    }

    private function getServiceConfiguration(string $serviceId): ?Configuration
    {
        if (!$this->serviceConfiguration instanceof Configuration) {
            $serviceConfiguration = $this->createConfiguration($serviceId, self::CONFIGURATION_FILENAME);

            if ($serviceConfiguration instanceof Configuration) {
                $this->serviceConfiguration = $serviceConfiguration;
            }
        }

        return $this->serviceConfiguration;
    }

    private function createConfiguration(string $serviceId, string $filename): ?Configuration
    {
        return $this->configurationFactory->create($this->getFilePath($serviceId, $filename));
    }

    private function jsonFileExists(string $serviceId, string $filename): bool
    {
        $filePath = $this->getFilePath($serviceId, $filename);

        return file_exists($filePath) && is_readable($filePath);
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
