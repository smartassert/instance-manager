<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\EnvironmentVariableCollection;

interface EnvironmentVariableCollectionLoaderInterface
{
    public function load(string $serviceId): EnvironmentVariableCollection;
}
