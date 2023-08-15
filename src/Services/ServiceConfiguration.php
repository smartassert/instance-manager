<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\ServiceConfigurationMissingException;
use App\Model\Configuration;
use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableCollection;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

class ServiceConfiguration
{
    public const ENV_VAR_FILENAME = 'env.json';
    public const CONFIGURATION_FILENAME = 'configuration.json';
    public const DOMAIN_FILENAME = 'domain.json';

    private EnvironmentVariableCollection $environmentVariables;
    private Configuration $domainConfiguration;

    public function __construct(
        private readonly string $configurationDirectory,
        private readonly string $defaultDomain,
        private readonly FilesystemOperator $filesystem,
    ) {
    }

    public function getEnvironmentVariables(string $serviceId): EnvironmentVariableCollection
    {
        if (!isset($this->environmentVariables)) {
            $collection = [];
            $configuration = $this->createConfiguration($serviceId, self::ENV_VAR_FILENAME);

            if ($configuration instanceof Configuration) {
                $data = $configuration->getAll();

                foreach ($data as $key => $value) {
                    if (is_string($key) && is_string($value)) {
                        $collection[] = new EnvironmentVariable($key, $value);
                    }
                }
            }

            $this->environmentVariables = new EnvironmentVariableCollection($collection);
        }

        return $this->environmentVariables;
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

    public function setServiceConfiguration(string $serviceId, string $healthCheckUrl, string $stateUrl): bool
    {
        $data = ['health_check_url' => $healthCheckUrl, 'state_url' => $stateUrl];

        $serviceConfigurationDirectory = $this->getServiceConfigurationDirectory($serviceId);

        try {
            if (!$this->filesystem->directoryExists($serviceConfigurationDirectory)) {
                $this->filesystem->createDirectory($serviceConfigurationDirectory);
            }
        } catch (FilesystemException) {
            return false;
        }

        $filePath = $this->getFilePath($serviceId, self::CONFIGURATION_FILENAME);

        try {
            $this->filesystem->write(
                $filePath,
                (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } catch (FilesystemException) {
            return false;
        }

        return true;
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
        $path = $this->getFilePath($serviceId, $filename);

        try {
            $content = $this->filesystem->read($path);

            $data = json_decode($content, true);
            $data = is_array($data) ? $data : [];

            return new Configuration($data);
        } catch (FilesystemException) {
            return null;
        }
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
