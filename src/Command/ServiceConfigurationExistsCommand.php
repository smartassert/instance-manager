<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\ServiceIdMissingException;
use App\Services\CommandConfigurator;
use App\Services\CommandServiceIdExtractor;
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

    public function __construct(
        private CommandConfigurator $configurator,
        private CommandServiceIdExtractor $serviceIdExtractor,
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

    /**
     * @throws ServiceIdMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->serviceIdExtractor->extract($input);

        return $this->serviceConfiguration->exists($serviceId) ? Command::SUCCESS : Command::FAILURE;
    }
}
