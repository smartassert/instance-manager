<?php

declare(strict_types=1);

namespace App\Exception;

class InstanceNotFoundException extends \Exception
{
    public function __construct(
        public readonly int $instanceId,
    ) {
        parent::__construct(sprintf('Instance "%d" not found', $instanceId));
    }
}
