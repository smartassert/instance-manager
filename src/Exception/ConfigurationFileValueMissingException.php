<?php

declare(strict_types=1);

namespace App\Exception;

class ConfigurationFileValueMissingException extends \Exception
{
    public function __construct(
        public readonly string $path,
        public readonly string $key,
        public readonly string $serviceId,
    ) {
        parent::__construct(sprintf(
            'Configuration file "%s" missing "%s" for service "%s"',
            $this->path,
            $this->key,
            $serviceId,
        ));
    }
}
