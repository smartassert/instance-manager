<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\UrlKey;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;

interface UrlLoaderInterface
{
    /**
     * @throws ConfigurationFileValueMissingException
     * @throws ServiceConfigurationMissingException
     */
    public function load(string $serviceId, UrlKey $key): string;
}
