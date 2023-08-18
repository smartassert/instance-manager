<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;

interface ImageIdLoaderInterface
{
    /**
     * @throws ConfigurationFileValueMissingException
     * @throws ServiceConfigurationMissingException
     */
    public function load(string $serviceId): int;
}
