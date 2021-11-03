<?php

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
}
