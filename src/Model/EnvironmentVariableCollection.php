<?php

declare(strict_types=1);

namespace App\Model;

class EnvironmentVariableCollection
{
    public function __construct(
        private readonly array $environmentVariables = [],
    ) {
    }
}
