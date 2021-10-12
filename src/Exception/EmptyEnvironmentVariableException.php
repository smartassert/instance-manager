<?php

namespace App\Exception;

class EmptyEnvironmentVariableException extends \RuntimeException
{
    public function __construct(
        private string $name
    ) {
        parent::__construct(sprintf(
            'Environment variable "%s" is not allowed to be empty',
            $name
        ));
    }

    public function getName(): string
    {
        return $this->name;
    }
}
