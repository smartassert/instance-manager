<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;
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
    private Configuration $serviceConfiguration;
    private Configuration $imageConfiguration;
    private Configuration $domainConfiguration;

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
     * @throws ConfigurationFileValueMissingException
     * @throws ServiceConfigurationMissingException
     */
    public function getImageId(string $serviceId): int
    {
        if (!isset($this->imageConfiguration)) {
            $this->imageConfiguration = $this->createConfigurationThrowingExceptionIfMissing(
                $serviceId,
                self::IMAGE_FILENAME
            );
        }

        $key = 'image_id';
        $imageId = $this->imageConfiguration->getInt($key);

        if (!is_int($imageId)) {
            throw new ConfigurationFileValueMissingException(self::IMAGE_FILENAME, $key, $serviceId);
        }

        return $imageId;
    }

    /**
     * @throws ServiceConfigurationMissingException
     */
    public function getDomain(string $serviceId): string
    {
        if (!isset($this->domainConfiguration)) {
            $this->domainConfiguration = $this->createConfigurationThrowingExceptionIfMissing(
                $serviceId,
                self::DOMAIN_FILENAME
            );
        }

        $domain = $this->domainConfiguration->getString('domain');

        return is_string($domain) ? $domain : $this->defaultDomain;
    }

    /**
     * @throws ServiceConfigurationMissingException
     * @throws ConfigurationFileValueMissingException
     */
    public function getHealthCheckUrl(string $serviceId): string
    {
        if (!isset($this->serviceConfiguration)) {
            $this->serviceConfiguration = $this->createConfigurationThrowingExceptionIfMissing(
                $serviceId,
                self::CONFIGURATION_FILENAME
            );
        }

        $key = 'health_check_url';
        $healthCheckUrl = $this->serviceConfiguration->getString($key);

        if (null === $healthCheckUrl) {
            throw new ConfigurationFileValueMissingException(self::CONFIGURATION_FILENAME, $key, $serviceId);
        }

        return $healthCheckUrl;
    }

    /**
     * @throws ServiceConfigurationMissingException
     * @throws ConfigurationFileValueMissingException
     */
    public function getStateUrl(string $serviceId): string
    {
        if (!isset($this->serviceConfiguration)) {
            $this->serviceConfiguration = $this->createConfigurationThrowingExceptionIfMissing(
                $serviceId,
                self::CONFIGURATION_FILENAME
            );
        }

        $key = 'state_url';
        $stateUrl = $this->serviceConfiguration->getString('state_url');

        if (null === $stateUrl) {
            throw new ConfigurationFileValueMissingException(self::CONFIGURATION_FILENAME, $key, $serviceId);
        }

        return $stateUrl;
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

    /**
     * @throws ServiceConfigurationMissingException
     */
    private function createConfigurationThrowingExceptionIfMissing(string $serviceId, string $filename): Configuration
    {
        $configuration = $this->createConfiguration($serviceId, $filename);
        if (null === $configuration) {
            throw new ServiceConfigurationMissingException($serviceId, $filename);
        }

        return $configuration;
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
