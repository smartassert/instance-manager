<?php

namespace App\Model;

/**
 * @implements \IteratorAggregate<int, EnvironmentVariable>
 */
class EnvironmentVariableList implements \IteratorAggregate
{
    /**
     * @var EnvironmentVariable[]
     */
    private array $environmentVariables;

    public function __construct(mixed $keyPairValues)
    {
        if (is_array($keyPairValues)) {
            $this->environmentVariables = $this->parseKeyPairValues($keyPairValues);
        }
    }

    /**
     * @return \Iterator<int, EnvironmentVariable>
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->environmentVariables);
    }

    /**
     * @param string[] $keyPairValues
     *
     * @return EnvironmentVariable[]
     */
    private function parseKeyPairValues(array $keyPairValues): array
    {
        $environmentVariables = [];

        foreach ($keyPairValues as $keyPairValue) {
            $environmentVariable = $this->parseKeyPairValue($keyPairValue);
            if ($environmentVariable instanceof EnvironmentVariable) {
                $environmentVariables[] = $environmentVariable;
            }
        }

        return $environmentVariables;
    }

    private function parseKeyPairValue(string $keyPairValue): ?EnvironmentVariable
    {
        $expectedPartCount = 2;

        $parts = explode('=', $keyPairValue, $expectedPartCount);
        if ($expectedPartCount === count($parts)) {
            if ('' !== $parts[0]) {
                return new EnvironmentVariable($parts[0], $parts[1]);
            }
        }

        return null;
    }
}
