<?php

declare(strict_types=1);

namespace App\Exception;

class ImageIdMissingException extends \Exception
{
    public function __construct(public readonly string $serviceId)
    {
        parent::__construct(sprintf('"image_id" missing for service "%s"', $serviceId));
    }
}
