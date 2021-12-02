<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\EnvironmentVariable;
use Doctrine\Common\Collections\Collection;

class BootScriptFactory
{
    /**
     * @param Collection<int, EnvironmentVariable> $environmentVariables
     */
    public function create(Collection $environmentVariables, string $serviceBootScript): string
    {
        if ('' === $serviceBootScript && $environmentVariables->isEmpty()) {
            return '';
        }

        return implode("\n", array_filter([
            '#!/usr/bin/env bash',
            $this->createEnvironmentVariableContent($environmentVariables),
            $serviceBootScript
        ]));
    }

    /**
     * @param Collection<int, EnvironmentVariable> $environmentVariables
     */
    private function createEnvironmentVariableContent(Collection $environmentVariables): string
    {
        $content = [];
        foreach ($environmentVariables as $environmentVariable) {
            $content[] = 'echo \'' . $environmentVariable . '\' >> /etc/environment';
        }

        return implode("\n", $content);
    }
}
