<?php

declare(strict_types=1);

namespace App\Services;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

readonly class ServiceConfigurationPersister
{
    public function __construct(
        private string $configurationDirectory,
        private FilesystemOperator $filesystem,
    ) {
    }

    public function persist(string $serviceId, string $filename, string $content): bool
    {
        $serviceConfigurationDirectory = $this->getServiceConfigurationDirectory($serviceId);

        try {
            if (!$this->filesystem->directoryExists($serviceConfigurationDirectory)) {
                $this->filesystem->createDirectory($serviceConfigurationDirectory);
            }
        } catch (FilesystemException) {
            return false;
        }

        $path = $this->getFilePath($serviceId, $filename);

        try {
            $this->filesystem->write($path, $content);
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
