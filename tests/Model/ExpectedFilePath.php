<?php

declare(strict_types=1);

namespace App\Tests\Model;

class ExpectedFilePath
{
    public static function create(string $serviceId, string $filename): string
    {
        return sprintf('/%s/%s', $serviceId, $filename);
    }
}
