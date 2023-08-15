<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Filename;
use App\Enum\UrlKey;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Model\Service\UrlCollection;

readonly class UrlCollectionLoader
{
    public function __construct(
        private ServiceConfigurationLoader $serviceConfigurationLoader,
    ) {
    }

    /**
     * @throws ServiceConfigurationMissingException
     * @throws ConfigurationFileValueMissingException
     */
    public function load(string $serviceId): UrlCollection
    {
        $filename = Filename::URL_COLLECTION->value;

        $data = $this->serviceConfigurationLoader->load($serviceId, $filename);
        if (null === $data) {
            throw new ServiceConfigurationMissingException($serviceId, $filename);
        }

        $healthCheckUrl = $data[UrlKey::HEALTH_CHECK->value] ?? null;
        if (!is_string($healthCheckUrl)) {
            throw new ConfigurationFileValueMissingException($filename, UrlKey::HEALTH_CHECK->value, $serviceId);
        }

        $stateUrl = $data[UrlKey::STATE->value] ?? null;
        if (!is_string($stateUrl)) {
            throw new ConfigurationFileValueMissingException($filename, UrlKey::STATE->value, $serviceId);
        }

        return new UrlCollection($healthCheckUrl, $stateUrl);
    }
}
