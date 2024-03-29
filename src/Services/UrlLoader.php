<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Filename;
use App\Enum\UrlKey;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;

readonly class UrlLoader implements UrlLoaderInterface
{
    public function __construct(
        private ServiceConfigurationOperator $configurationOperator,
    ) {
    }

    /**
     * @throws ConfigurationFileValueMissingException
     * @throws ServiceConfigurationMissingException
     */
    public function load(string $serviceId, UrlKey $key): string
    {
        $filename = Filename::URL_COLLECTION->value;

        $data = $this->configurationOperator->load($serviceId, $filename);
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
