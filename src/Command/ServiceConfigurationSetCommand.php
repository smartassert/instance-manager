<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\ServiceIdMissingException;
use App\Services\CommandConfigurator;
use App\Services\ServiceConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: ServiceConfigurationSetCommand::NAME,
    description: 'Set configuration values for service',
)]
class ServiceConfigurationSetCommand extends AbstractServiceCommand
{
    public const NAME = 'app:service-configuration:set';

    public const OPTION_HEALTH_CHECK_URL = 'health-check-url';
    public const OPTION_STATE_URL = 'state-url';

    public const EXIT_CODE_EMPTY_HEALTH_CHECK_URL = 4;
    public const EXIT_CODE_EMPTY_STATE_URL = 5;

    public function __construct(
        CommandConfigurator $configurator,
        private ServiceConfiguration $serviceConfiguration,
    ) {
        parent::__construct($configurator);
    }

    protected function configure(): void
    {
        $this->configurator
            ->addServiceIdOption($this)
        ;

        $this
            ->addOption(
                self::OPTION_HEALTH_CHECK_URL,
                '',
                InputOption::VALUE_REQUIRED,
                'URL for performing an instance health check'
            )
            ->addOption(
                self::OPTION_STATE_URL,
                '',
                InputOption::VALUE_REQUIRED,
                'URL for getting instance state'
            )
        ;
    }

    /**
     * @throws ServiceIdMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);

        $healthCheckUrl = $input->getOption(self::OPTION_HEALTH_CHECK_URL);
        $healthCheckUrl = is_string($healthCheckUrl) ? trim($healthCheckUrl) : '';
        if ('' === $healthCheckUrl) {
            $output->writeln('"' . self::OPTION_HEALTH_CHECK_URL . '" option empty');

            return self::EXIT_CODE_EMPTY_HEALTH_CHECK_URL;
        }

        $stateUrl = $input->getOption(self::OPTION_STATE_URL);
        $stateUrl = is_string($stateUrl) ? trim($stateUrl) : '';
        if ('' === $stateUrl) {
            $output->writeln('"' . self::OPTION_STATE_URL . '" option empty');

            return self::EXIT_CODE_EMPTY_STATE_URL;
        }

        $result = $this->serviceConfiguration->setServiceConfiguration($serviceId, $healthCheckUrl, $stateUrl);

        return $result ? Command::SUCCESS : Command::FAILURE;
    }
}
