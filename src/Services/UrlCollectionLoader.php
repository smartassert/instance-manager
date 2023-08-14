<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Filename;
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
     */
    public function load(string $serviceId): UrlCollection
    {
        $data = $this->serviceConfigurationLoader->load($serviceId, Filename::URL_COLLECTION->value);
        if (null === $data) {
            throw new ServiceConfigurationMissingException($serviceId, Filename::URL_COLLECTION->value);
        }

        $healthCheckUrl = $data['health_check_url'] ?? null;
        $healthCheckUrl = is_string($healthCheckUrl) ? $healthCheckUrl : null;

        $stateUrl = $data['state_url'] ?? null;
        $stateUrl = is_string($stateUrl) ? $stateUrl : null;

        return new UrlCollection($healthCheckUrl, $stateUrl);
    }
}
