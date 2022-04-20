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
    name: InstanceIsReadyCommand::NAME,
    description: 'Check if an instance is ready to be used',
)]
class InstanceIsReadyCommand extends AbstractServiceCommand
{
    use RetryableCommandTrait;

    public const NAME = 'app:instance:is-ready';
    public const EXIT_CODE_SERVICE_CONFIGURATION_MISSING = 6;
    public const EXIT_CODE_SERVICE_STATE_URL_MISSING = 7;

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

        $stateUrl = $this->serviceConfiguration->getStateUrl($serviceId);
        if (null === $stateUrl || '' === $stateUrl) {
            $output->write('No state_url for service "' . $serviceId . '"');

            return self::EXIT_CODE_SERVICE_STATE_URL_MISSING;
        }

        $instance = $this->commandInstanceRepository->get($input);
        if (null === $instance) {
            $output->write($this->commandInstanceRepository->getErrorMessage());

            return $this->commandInstanceRepository->getErrorCode();
        }

        $result = $this->commandActionRunner->run(
            $this->getRetryLimit($input),
            $this->getRetryDelay($input),
            $output,
            function (bool $isLastAttempt) use ($stateUrl, $instance, $output): bool {
                $state = $this->instanceClient->getState($stateUrl, $instance);

                $isReady = $state['ready'] ?? null;
                $isReady = is_bool($isReady) ? $isReady : true;

                $output->write($isReady ? 'ready' : 'not-ready');

                if (false === $isReady && false === $isLastAttempt) {
                    $output->writeln('');
                }

                return $isReady;
            }
        );

        return true === $result ? Command::SUCCESS : Command::FAILURE;
    }
}
