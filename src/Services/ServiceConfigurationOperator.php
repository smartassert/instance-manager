<?php

declare(strict_types=1);

namespace App\Services;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

readonly class ServiceConfigurationOperator
{
    public function __construct(
        private FilesystemOperator $filesystem,
    ) {}

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
        return '/' . $serviceId;
    }

    private function getFilePath(string $serviceId, string $filename): string
    {
        return $this->getServiceConfigurationDirectory($serviceId) . '/' . $filename;
    }
}
