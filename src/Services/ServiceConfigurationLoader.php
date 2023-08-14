<?php

declare(strict_types=1);

namespace App\Services;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

readonly class ServiceConfigurationLoader
{
    public function __construct(
        private string $configurationDirectory,
        private FilesystemOperator $filesystem,
    ) {
    }

    /**
     * @return ?array<mixed>
     */
    public function load(string $serviceId, string $filename): ?array
    {
        $path = $this->getFilePath($serviceId, $filename);

        try {
            $content = $this->filesystem->read($path);

            $data = json_decode($content, true);

            return is_array($data) ? $data : [];
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
