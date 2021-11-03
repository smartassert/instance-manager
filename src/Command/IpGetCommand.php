<?php

namespace App\Command;

use App\Model\AssignedIp;
use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\FloatingIpRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: IpGetCommand::NAME,
    description: 'Get the current floating IP',
)]
class IpGetCommand extends Command
{
    public const NAME = 'app:ip:get';
    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 3;

    public function __construct(
        private FloatingIpRepository $floatingIpRepository,
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
    ) {
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this->configurator->addCollectionTagOption($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $collectionTag = $this->inputReader->getTrimmedStringOption(CommandConfigurator::OPTION_COLLECTION_TAG, $input);
        if ('' === $collectionTag) {
            $output->writeln('"' . CommandConfigurator::OPTION_COLLECTION_TAG . '" option empty');

            return self::EXIT_CODE_EMPTY_COLLECTION_TAG;
        }

        $assignedIp = $this->floatingIpRepository->find($collectionTag);
        if ($assignedIp instanceof AssignedIp) {
            $output->write($assignedIp->getIp());
        }

        return Command::SUCCESS;
    }
}
