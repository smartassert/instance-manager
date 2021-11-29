<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Console\Input\InputInterface;

class CommandInputReader
{
    public function getTrimmedStringOption(string $name, InputInterface $input): string
    {
        $value = $input->getOption($name);
        if (!is_string($value)) {
            $value = '';
        }

        return trim($value);
    }

    public function getIntegerOption(string $name, InputInterface $input): ?int
    {
        $value = $input->getOption($name);

        return is_int($value) || is_string($value) && ctype_digit($value) ? (int) $value : null;
    }
}
