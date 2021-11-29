<?php

declare(strict_types=1);

namespace App\Command;

use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\ServiceConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: ServiceConfigurationExistsCommand::NAME,
    description: 'Check if configuration exists for a given service_id',
)]
class ServiceConfigurationExistsCommand extends Command
{
    public const NAME = 'app:service-configuration:exists';

    public const EXIT_CODE_EMPTY_SERVICE_ID = 3;

    public function __construct(
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
        private ServiceConfiguration $serviceConfiguration,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator
            ->addServiceIdOption($this)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->inputReader->getTrimmedStringOption(Option::OPTION_SERVICE_ID, $input);
        if ('' === $serviceId) {
            $output->writeln('"' . Option::OPTION_SERVICE_ID . '" option empty');

            return self::EXIT_CODE_EMPTY_SERVICE_ID;
        }

        return $this->serviceConfiguration->exists($serviceId) ? Command::SUCCESS : Command::FAILURE;
    }
}
