<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\ServiceConfigurationMissingException;

interface DomainLoaderInterface
{
    /**
     * @throws ServiceConfigurationMissingException
     */
    public function load(string $serviceId): string;
}
