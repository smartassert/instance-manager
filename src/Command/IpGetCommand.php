<?php

declare(strict_types=1);

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
        $this->configurator->addServiceIdOption($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->inputReader->getTrimmedStringOption(Option::OPTION_SERVICE_ID, $input);
        if ('' === $serviceId) {
            $output->writeln('"' . Option::OPTION_SERVICE_ID . '" option empty');

            return self::EXIT_CODE_EMPTY_COLLECTION_TAG;
        }

        $assignedIp = $this->floatingIpRepository->find($serviceId);
        if ($assignedIp instanceof AssignedIp) {
            $output->write($assignedIp->getIp());
        }

        return Command::SUCCESS;
    }
}
