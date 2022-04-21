<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Console\Input\InputInterface;

class CommandInputReader
{
    public function getIntegerOption(string $name, InputInterface $input): ?int
    {
        $value = $input->getOption($name);

        return is_int($value) || is_string($value) && ctype_digit($value) ? (int) $value : null;
    }
}
