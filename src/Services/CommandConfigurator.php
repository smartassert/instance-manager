<?php

namespace App\Services;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class CommandConfigurator
{
    public const OPTION_COLLECTION_TAG = 'collection-tag';

    public function addCollectionTagOption(Command $command): void
    {
        $command->addOption(
            self::OPTION_COLLECTION_TAG,
            null,
            InputOption::VALUE_REQUIRED,
            'Tag applied to all instances'
        );
    }
}
