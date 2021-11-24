<?php

namespace App\Command;

use App\Services\CommandActionRunner;
use App\Services\CommandConfigurator;
use App\Services\CommandInstanceRepository;
use App\Services\InstanceClient;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceIsReadyCommand::NAME,
    description: 'Check if an instance is ready to be used',
)]
class InstanceIsReadyCommand extends Command
{
    use RetryableCommandTrait;

    public const NAME = 'app:instance:is-ready';
    public const EXIT_CODE_ID_INVALID = 3;
    public const EXIT_CODE_NOT_FOUND = 4;

    public const OPTION_RETRY_LIMIT = 'retry-limit';
    public const OPTION_RETRY_DELAY = 'retry-delay';
    public const DEFAULT_RETRY_LIMIT = 5;
    public const DEFAULT_RETRY_DELAY = 30;

    public function __construct(
        private InstanceClient $instanceClient,
        private CommandActionRunner $commandActionRunner,
        private CommandConfigurator $configurator,
        private CommandInstanceRepository $commandInstanceRepository,
    ) {
        parent::__construct();
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
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $instance = $this->commandInstanceRepository->get($input);
        if (null === $instance) {
            $output->write($this->commandInstanceRepository->getErrorMessage());

            return $this->commandInstanceRepository->getErrorCode();
        }

        $result = $this->commandActionRunner->run(
            $this->getRetryLimit($input),
            $this->getRetryDelay($input),
            $output,
            function (bool $isLastAttempt) use ($output, $instance): bool {
                $state = $this->instanceClient->getState($instance);

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
