<?php

namespace App\Services;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class CommandConfigurator
{
    public const OPTION_COLLECTION_TAG = 'collection-tag';
    public const OPTION_IMAGE_ID = 'image-id';

    public function addCollectionTagOption(Command $command): self
    {
        $command->addOption(
            self::OPTION_COLLECTION_TAG,
            null,
            InputOption::VALUE_REQUIRED,
            'Tag applied to all instances'
        );

        return $this;
    }

    public function addImageIdOption(Command $command): self
    {
        $command->addOption(
            self::OPTION_IMAGE_ID,
            null,
            InputOption::VALUE_REQUIRED,
            'ID of image (snapshot)'
        );

        return $this;
    }
}
