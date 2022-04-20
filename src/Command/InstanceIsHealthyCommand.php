<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\ServiceIdMissingException;
use App\Services\CommandActionRunner;
use App\Services\CommandConfigurator;
use App\Services\CommandInstanceRepository;
use App\Services\InstanceClient;
use App\Services\ServiceConfiguration;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceIsHealthyCommand::NAME,
    description: 'Perform instance health check',
)]
class InstanceIsHealthyCommand extends AbstractServiceCommand
{
    use RetryableCommandTrait;

    public const NAME = 'app:instance:is-healthy';
    public const EXIT_CODE_SERVICE_CONFIGURATION_MISSING = 6;

    public const OPTION_RETRY_LIMIT = 'retry-limit';
    public const OPTION_RETRY_DELAY = 'retry-delay';
    public const DEFAULT_RETRY_LIMIT = 5;
    public const DEFAULT_RETRY_DELAY = 30;

    public function __construct(
        CommandConfigurator $configurator,
        private InstanceClient $instanceClient,
        private CommandActionRunner $commandActionRunner,
        private CommandInstanceRepository $commandInstanceRepository,
        private ServiceConfiguration $serviceConfiguration,
    ) {
        parent::__construct($configurator);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->configurator
            ->addId($this)
            ->addRetryLimitOption($this, self::DEFAULT_RETRY_LIMIT)
            ->addRetryDelayOption($this, self::DEFAULT_RETRY_DELAY)
        ;
    }

    /**
     * @throws ExceptionInterface
     * @throws ServiceIdMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);

        if (false === $this->serviceConfiguration->exists($serviceId)) {
            $output->write('No configuration for service "' . $serviceId . '"');

            return self::EXIT_CODE_SERVICE_CONFIGURATION_MISSING;
        }

        $instance = $this->commandInstanceRepository->get($input);
        if (null === $instance) {
            $output->write($this->commandInstanceRepository->getErrorMessage());

            return $this->commandInstanceRepository->getErrorCode();
        }

        $healthCheckUrl = $this->serviceConfiguration->getHealthCheckUrl($serviceId);
        if (null === $healthCheckUrl || '' === $healthCheckUrl) {
            return Command::SUCCESS;
        }

        $result = $this->commandActionRunner->run(
            $this->getRetryLimit($input),
            $this->getRetryDelay($input),
            $output,
            function (bool $isLastAttempt) use ($healthCheckUrl, $output, $instance): bool {
                $response = $this->instanceClient->getHealth($healthCheckUrl, $instance);
                $isHealthy = 200 === $response->getStatusCode();

                $output->write($response->getBody()->getContents());

                if (false === $isHealthy && false === $isLastAttempt) {
                    $output->writeln('');
                }

                return $isHealthy;
            }
        );

        return true === $result ? Command::SUCCESS : Command::FAILURE;
    }
}
