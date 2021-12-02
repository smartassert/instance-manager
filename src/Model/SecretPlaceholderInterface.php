<?php

declare(strict_types=1);

namespace App\Model;

interface SecretPlaceholderInterface extends \Stringable
{
    public static function is(string $value): bool;

    public function getSecretName(): string;
}
