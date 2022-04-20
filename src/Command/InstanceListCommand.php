<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\ServiceIdMissingException;
use App\Services\CommandConfigurator;
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
class InstanceListCommand extends AbstractServiceCommand
{
    public const NAME = 'app:instance:list';

    public const EXIT_CODE_SERVICE_CONFIGURATION_MISSING = 6;

    public function __construct(
        CommandConfigurator $configurator,
        private InstanceRepository $instanceRepository,
        private ServiceConfiguration $serviceConfiguration,
    ) {
        parent::__construct($configurator);
    }

    /**
     * @throws ExceptionInterface
     * @throws ServiceIdMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);
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
