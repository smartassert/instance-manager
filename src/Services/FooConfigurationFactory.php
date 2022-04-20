<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\FooConfiguration;

class FooConfigurationFactory
{
    public function exists(string $path): bool
    {
        return file_exists($path) && is_readable($path);
    }

    public function foo(string $path): ?FooConfiguration
    {
        if (false === (file_exists($path) && is_readable($path))) {
            return null;
        }

        $content = (string) file_get_contents($path);
        $data = json_decode($content, true);
        $data = is_array($data) ? $data : [];

        return new FooConfiguration($data);
    }
}
