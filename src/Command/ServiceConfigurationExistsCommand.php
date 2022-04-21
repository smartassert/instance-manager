<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\RequiredOptionMissingException;
use App\Services\CommandConfigurator;
use App\Services\ServiceConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: ServiceConfigurationExistsCommand::NAME,
    description: 'Check if configuration exists for a given service_id',
)]
class ServiceConfigurationExistsCommand extends AbstractServiceCommand
{
    public const NAME = 'app:service-configuration:exists';

    public function __construct(
        CommandConfigurator $configurator,
        private ServiceConfiguration $serviceConfiguration,
    ) {
        parent::__construct($configurator);
    }

    /**
     * @throws RequiredOptionMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);

        return $this->serviceConfiguration->exists($serviceId) ? Command::SUCCESS : Command::FAILURE;
    }
}
