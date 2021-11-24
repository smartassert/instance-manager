<?php

namespace App\Model;

class EnvironmentVariable extends KeyValue implements SecretPlaceholderContainerInterface
{
    public function __toString(): string
    {
        $key = $this->getKey();
        if ('' === $key) {
            return '';
        }

        return $key . '="' . str_replace('"', '\\"', $this->getValue()) . '"';
    }

    public function getSecretPlaceholder(): ?SecretPlaceholderInterface
    {
        $value = $this->getValue();

        return SecretPlaceholder::is($value) ? new SecretPlaceholder($value) : null;
    }

    public function replace(
        SecretPlaceholderInterface $placeholder,
        string $secret
    ): SecretPlaceholderContainerInterface {
        return new EnvironmentVariable($this->getKey(), $secret);
    }

    public function equals(SecretPlaceholderContainerInterface $placeholderContainer): bool
    {
        return (string) $this === (string) $placeholderContainer;
    }
}
