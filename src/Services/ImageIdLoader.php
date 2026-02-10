<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Filename;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;

readonly class ImageIdLoader implements ImageIdLoaderInterface
{
    public function __construct(
        private ServiceConfigurationOperator $serviceConfigurationOperator,
    ) {}

    /**
     * @throws ConfigurationFileValueMissingException
     * @throws ServiceConfigurationMissingException
     */
    public function load(string $serviceId): int
    {
        $filename = Filename::IMAGE->value;

        $data = $this->serviceConfigurationOperator->load($serviceId, $filename);
        if (null === $data) {
            throw new ServiceConfigurationMissingException($serviceId, $filename);
        }

        $value = $data['image_id'] ?? null;
        if (!(is_int($value) || is_numeric($value))) {
            throw new ConfigurationFileValueMissingException($filename, 'image_id', $serviceId);
        }

        return (int) $value;
    }
}
