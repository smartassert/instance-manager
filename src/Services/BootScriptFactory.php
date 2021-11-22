<?php

namespace App\Services;

class BootScriptFactory
{
    public function __construct(
        private string $serviceScriptCaller,
        private ServiceConfiguration $serviceConfiguration,
    ) {
    }

    public function create(string $serviceId): string
    {
        $script = $this->createEnvironmentVariableExportStatements($serviceId);
        if ('' !== $script && '' !== $this->serviceScriptCaller) {
            $script .= "\n";
        }

        return $script . $this->serviceScriptCaller;
    }

    private function createEnvironmentVariableExportStatements(string $serviceId): string
    {
        $statements = '';

        $environmentVariables = $this->serviceConfiguration->getEnvironmentVariables($serviceId);
        foreach ($environmentVariables as $environmentVariable) {
            $statements .= 'export ' . $environmentVariable . "\n";
        }

        return trim($statements);
    }
}
