<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: InstanceListCommand::NAME,
    description: 'List instances',
)]
class InstanceListCommand extends AbstractInstanceListCommand
{
    public const NAME = 'app:instance:list';
}
