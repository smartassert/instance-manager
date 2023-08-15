<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Filename;
use App\Enum\UrlKey;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;

readonly class UrlCollectionLoader
{
    public function __construct(
        private ServiceConfigurationLoader $serviceConfigurationLoader,
    ) {
    }

    /**
     * @throws ConfigurationFileValueMissingException
     * @throws ServiceConfigurationMissingException
     */
    public function load(string $serviceId, UrlKey $key): string
    {
        $filename = Filename::URL_COLLECTION->value;

        $data = $this->serviceConfigurationLoader->load($serviceId, $filename);
        if (null === $data) {
            throw new ServiceConfigurationMissingException($serviceId, $filename);
        }

        $value = $data[$key->value] ?? null;
        if (!is_string($value)) {
            throw new ConfigurationFileValueMissingException($filename, $key->value, $serviceId);
        }

        return $value;
    }
}
