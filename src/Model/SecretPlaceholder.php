<?php

namespace App\Model;

class SecretPlaceholder implements SecretPlaceholderInterface
{
    public const PREFIX = '{{ secrets.';
    public const SUFFIX = ' }}';

    public function __construct(
        private string $placeholder
    ) {
    }

    public static function is(string $value): bool
    {
        $pattern = sprintf(
            '/^%s%s%s$/',
            preg_quote(self::PREFIX, '/'),
            '[A-Za-z0-9_]+',
            preg_quote(self::SUFFIX, '/'),
        );

        return 1 === preg_match($pattern, $value);
    }

    public function getSecretName(): string
    {
        return (string) preg_replace(
            [
                '/^' . preg_quote(self::PREFIX, '/') . '/',
                '/' . preg_quote(self::SUFFIX, '/') . '$/',
            ],
            '',
            $this->placeholder
        );
    }
}
