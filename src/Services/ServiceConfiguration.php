<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\EnvironmentVariable;
use App\Model\FooConfiguration;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;
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
    private ?FooConfiguration $fooServiceConfiguration = null;
    private ?FooConfiguration $imageConfiguration = null;
    private ?FooConfiguration $domainConfiguration = null;

    public function __construct(
        private readonly FooConfigurationFactory $fooConfigurationFactory,
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

            $foo = $this->getFooConfiguration($serviceId, self::ENV_VAR_FILENAME);

            if ($foo instanceof FooConfiguration) {
                $data = $foo->getAll();

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

    public function getImageId(string $serviceId): ?int
    {
        if (!$this->imageConfiguration instanceof FooConfiguration) {
            $this->imageConfiguration = $this->getFooConfiguration($serviceId, self::IMAGE_FILENAME);
        }

        return $this->imageConfiguration instanceof FooConfiguration
            ? $this->imageConfiguration->getInt('image_id')
            : null;
    }

    public function getDomain(string $serviceId): string
    {
        if (!$this->domainConfiguration instanceof FooConfiguration) {
            $this->domainConfiguration = $this->getFooConfiguration($serviceId, self::DOMAIN_FILENAME);
        }

        $domain = $this->domainConfiguration instanceof FooConfiguration
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

    public function setServiceConfiguration(ServiceConfigurationModel $serviceConfiguration): bool
    {
        $data = [
            ServiceConfigurationModel::KEY_HEALTH_CHECK_URL => $serviceConfiguration->getHealthCheckUrl(),
            ServiceConfigurationModel::KEY_STATE_URL => $serviceConfiguration->getStateUrl(),
        ];

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

    private function getServiceConfiguration(string $serviceId): ?FooConfiguration
    {
        if (!$this->fooServiceConfiguration instanceof FooConfiguration) {
            $serviceConfiguration = $this->getFooConfiguration($serviceId, self::CONFIGURATION_FILENAME);

            if ($serviceConfiguration instanceof FooConfiguration) {
                $this->fooServiceConfiguration = $serviceConfiguration;
            }
        }

        return $this->fooServiceConfiguration;
    }

    private function getFooConfiguration(string $serviceId, string $filename): ?FooConfiguration
    {
        return $this->fooConfigurationFactory->foo($this->getFilePath($serviceId, $filename));
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
