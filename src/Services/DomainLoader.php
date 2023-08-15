<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Filename;
use App\Exception\ServiceConfigurationMissingException;

readonly class DomainLoader implements DomainLoaderInterface
{
    public function __construct(
        private ServiceConfigurationLoader $serviceConfigurationLoader,
        private readonly string $defaultDomain,
    ) {
    }

    /**
     * @throws ServiceConfigurationMissingException
     */
    public function load(string $serviceId): string
    {
        $filename = Filename::DOMAIN->value;

        $data = $this->serviceConfigurationLoader->load($serviceId, $filename);
        if (null === $data) {
            throw new ServiceConfigurationMissingException($serviceId, $filename);
        }

        $value = $data['DOMAIN'] ?? null;

        return is_string($value) ? $value : $this->defaultDomain;
    }
}
