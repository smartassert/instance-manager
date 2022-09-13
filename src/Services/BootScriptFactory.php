<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\EnvironmentVariableCollection;

class BootScriptFactory
{
    public function create(EnvironmentVariableCollection $environmentVariables, string $serviceBootScript): string
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

    public function validate(string $script): bool
    {
        $comparator = trim($script);

        return '' === $comparator || str_starts_with($comparator, '#!');
    }

    private function createEnvironmentVariableContent(EnvironmentVariableCollection $environmentVariables): string
    {
        $content = [];
        foreach ($environmentVariables as $environmentVariable) {
            $content[] = 'export ' . $environmentVariable;
        }

        return implode("\n", $content);
    }
}
