<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Filename;
use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableCollection;

readonly class EnvironmentVariableCollectionLoader implements EnvironmentVariableCollectionLoaderInterface
{
    public function __construct(
        private ServiceConfigurationOperator $configurationOperator,
    ) {}

    public function load(string $serviceId): EnvironmentVariableCollection
    {
        $filename = Filename::ENVIRONMENT_VARIABLES->value;

        $data = $this->configurationOperator->load($serviceId, $filename);
        $data = is_array($data) ? $data : [];

        $collection = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $collection[] = new EnvironmentVariable($key, $value);
            }
        }

        return new EnvironmentVariableCollection($collection);
    }
}
