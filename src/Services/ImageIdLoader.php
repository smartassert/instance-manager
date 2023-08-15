<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Filename;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;

readonly class ImageIdLoader
{
    public function __construct(
        private ServiceConfigurationLoader $serviceConfigurationLoader,
    ) {
    }

    /**
     * @throws ConfigurationFileValueMissingException
     * @throws ServiceConfigurationMissingException
     */
    public function load(string $serviceId): string
    {
        $filename = Filename::IMAGE->value;

        $data = $this->serviceConfigurationLoader->load($serviceId, $filename);
        if (null === $data) {
            throw new ServiceConfigurationMissingException($serviceId, $filename);
        }

        $value = $data['image_id'] ?? null;
        if (!is_string($value)) {
            throw new ConfigurationFileValueMissingException($filename, 'image_id', $serviceId);
        }

        return $value;
    }
}
