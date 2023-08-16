<?php

declare(strict_types=1);

namespace App\Services;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

class ServiceConfiguration
{
    public const CONFIGURATION_FILENAME = 'configuration.json';

    public function __construct(
        private readonly string $configurationDirectory,
        private readonly FilesystemOperator $filesystem,
    ) {
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

    private function getServiceConfigurationDirectory(string $serviceId): string
    {
        return $this->configurationDirectory . '/' . $serviceId;
    }

    private function getFilePath(string $serviceId, string $filename): string
    {
        return $this->getServiceConfigurationDirectory($serviceId) . '/' . $filename;
    }
}
