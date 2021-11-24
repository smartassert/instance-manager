<?php

namespace App\Services;

use App\Command\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class CommandConfigurator
{
    public const OPTION_RETRY_LIMIT = 'retry-limit';
    public const OPTION_RETRY_DELAY = 'retry-delay';
    public const OPTION_ID = 'id';

    public function addCollectionTagOption(Command $command): self
    {
        $command->addOption(
            Option::OPTION_COLLECTION_TAG,
            null,
            InputOption::VALUE_REQUIRED,
            'Tag applied to all instances'
        );

        return $this;
    }

    public function addImageIdOption(Command $command): self
    {
        $command->addOption(
            Option::OPTION_IMAGE_ID,
            null,
            InputOption::VALUE_REQUIRED,
            'ID of image (snapshot)'
        );

        return $this;
    }

    public function addRetryLimitOption(Command $command, int $default): self
    {
        $command->addOption(
            self::OPTION_RETRY_LIMIT,
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
            self::OPTION_RETRY_DELAY,
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
            self::OPTION_ID,
            null,
            InputOption::VALUE_REQUIRED,
            'ID of the instance'
        );

        return $this;
    }
}
