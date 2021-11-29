<?php

declare(strict_types=1);

namespace App\Model;

interface SecretPlaceholderContainerInterface
{
    public function __toString(): string;

    public function getSecretPlaceholder(): ?SecretPlaceholderInterface;

    public function replace(SecretPlaceholderInterface $placeholder, string $secret): self;

    public function equals(SecretPlaceholderContainerInterface $placeholderContainer): bool;
}
