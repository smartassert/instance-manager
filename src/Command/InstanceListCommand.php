<?php

declare(strict_types=1);

namespace App\Command;

use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\InstanceRepository;
use App\Services\ServiceConfiguration;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceListCommand::NAME,
    description: 'List instances',
)]
class InstanceListCommand extends Command
{
    public const NAME = 'app:instance:list';

    public const EXIT_CODE_EMPTY_SERVICE_ID = 5;
    public const EXIT_CODE_SERVICE_CONFIGURATION_MISSING = 6;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
        private ServiceConfiguration $serviceConfiguration,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator->addServiceIdOption($this);
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->inputReader->getTrimmedStringOption(Option::OPTION_SERVICE_ID, $input);
        if ('' === $serviceId) {
            $output->write('"' . Option::OPTION_SERVICE_ID . '" option empty');

            return self::EXIT_CODE_EMPTY_SERVICE_ID;
        }

        $serviceConfiguration = $this->serviceConfiguration->getServiceConfiguration($serviceId);
        if (null === $serviceConfiguration) {
            $output->write('No configuration for service "' . $serviceId . '"');

            return self::EXIT_CODE_SERVICE_CONFIGURATION_MISSING;
        }

        $output->write((string) json_encode(
            $this->instanceRepository->findAll($serviceConfiguration->getServiceId())
        ));

        return Command::SUCCESS;
    }
}
