<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Destroy an instance',
)]
class InstanceDestroyCommand extends AbstractInstanceActionCommand
{
    public const NAME = 'app:instance:destroy';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $id = $this->getId();
        if (is_int($id)) {
            $this->instanceRepository->delete($id);
        }

        return Command::SUCCESS;
    }
}
