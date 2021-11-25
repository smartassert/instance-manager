<?php

namespace App\Services;

use App\Command\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class CommandConfigurator
{
    public function addServiceIdOption(Command $command): self
    {
        $command->addOption(
            Option::OPTION_SERVICE_ID,
            null,
            InputOption::VALUE_REQUIRED,
            'Tag applied to all instances'
        );

        return $this;
    }

    public function addRetryLimitOption(Command $command, int $default): self
    {
        $command->addOption(
            Option::OPTION_RETRY_LIMIT,
            null,
            InputOption::VALUE_REQUIRED,
            'How many times to retry command is not successful',
            $default
        );

        return $this;
    }

    public function addRetryDelayOption(Command $command, int $default): self
    {
        $command->addOption(
            Option::OPTION_RETRY_DELAY,
            null,
            InputOption::VALUE_REQUIRED,
            'How long to wait, in seconds, if command is not successful',
            $default
        );

        return $this;
    }

    public function addId(Command $command): self
    {
        $command->addOption(
            Option::OPTION_ID,
            null,
            InputOption::VALUE_REQUIRED,
            'ID of the instance'
        );

        return $this;
    }
}
