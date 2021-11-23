<?php

namespace App\Model;

/**
 * @iterable<int, EnvironmentVariable>
 */
class EnvironmentVariableList implements SecretPlaceholderContainerCollectionInterface
{
    /**
     * @var EnvironmentVariable[]
     */
    private array $environmentVariables;

    private int $iteratorPosition = 0;

    public function __construct(mixed $keyPairValues)
    {
        if (is_array($keyPairValues)) {
            $this->environmentVariables = $this->parseKeyPairValues($keyPairValues);
        }
    }

    public function set(
        SecretPlaceholderContainerInterface $placeholderContainer
    ): SecretPlaceholderContainerCollectionInterface {
        if ($placeholderContainer instanceof EnvironmentVariable) {
            $new = clone $this;
            foreach ($new->environmentVariables as $index => $environmentVariable) {
                if ($environmentVariable->getKey() === $placeholderContainer->getKey()) {
                    $new->environmentVariables[$index] = new EnvironmentVariable(
                        $placeholderContainer->getKey(),
                        $placeholderContainer->getValue()
                    );
                }
            }

            return $new;
        }

        return $this;
    }

    public function current(): SecretPlaceholderContainerInterface
    {
        return $this->environmentVariables[$this->iteratorPosition];
    }

    public function next(): void
    {
        ++$this->iteratorPosition;
    }

    public function key(): int
    {
        return $this->iteratorPosition;
    }

    public function valid(): bool
    {
        return isset($this->environmentVariables[$this->iteratorPosition]);
    }

    public function rewind(): void
    {
        $this->iteratorPosition = 0;
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
