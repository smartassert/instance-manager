<?php

declare(strict_types=1);

namespace App\Exception;

class ServiceConfigurationMissingException extends \Exception
{
    public function __construct(
        public readonly string $serviceId,
        public readonly string $filename,
    ) {
        parent::__construct(sprintf('"%s" missing for service "%s"', $filename, $serviceId));
    }
}
