<?php

namespace App\Command;

use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\InstanceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Destroy an instance',
)]
class InstanceDestroyCommand extends Command
{
    public const NAME = 'app:instance:destroy';

    public function __construct(
        private CommandConfigurator $configurator,
        private CommandInputReader $commandInputReader,
        private InstanceRepository $instanceRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator->addId($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $this->commandInputReader->getIntegerOption(Option::OPTION_ID, $input);
        if (is_int($id)) {
            $this->instanceRepository->delete($id);
        }

        return Command::SUCCESS;
    }
}
