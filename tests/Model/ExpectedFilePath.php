<?php

declare(strict_types=1);

namespace App\Tests\Model;

class ExpectedFilePath
{
    public static function create(string $configurationDirectory, string $serviceId, string $filename): string
    {
        return sprintf(
            '%s/%s/%s',
            $configurationDirectory,
            $serviceId,
            $filename
        );
    }
}
