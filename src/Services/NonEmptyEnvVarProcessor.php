<?php

namespace App\Services;

use App\Exception\EmptyEnvironmentVariableException;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class NonEmptyEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv)
    {
        $env = $getEnv($name);

        if (is_string($env)) {
            $trimmedEnv = trim($env);
            if ('' === $trimmedEnv) {
                throw new EmptyEnvironmentVariableException($name);
            }
        }

        return $env;
    }

    /**
     * @return string[]
     */
    public static function getProvidedTypes(): array
    {
        return [
            'non-empty' => 'string',
        ];
    }
}
