<?php

namespace App\Command;

use App\Services\CommandActionRunner;
use App\Services\InstanceClient;
use App\Services\InstanceRepository;
use App\Services\OutputFactory;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceIsHealthyCommand::NAME,
    description: 'Perform instance health check',
)]
class InstanceIsHealthyCommand extends AbstractInstanceObjectCommand
{
    use RetryableCommandTrait;

    public const NAME = 'app:instance:is-healthy';
    public const EXIT_CODE_ID_INVALID = 3;
    public const EXIT_CODE_NOT_FOUND = 4;

    public const OPTION_RETRY_LIMIT = 'retry-limit';
    public const OPTION_RETRY_DELAY = 'retry-delay';
    public const DEFAULT_RETRY_LIMIT = 5;
    public const DEFAULT_RETRY_DELAY = 30;

    public function __construct(
        InstanceRepository $instanceRepository,
        OutputFactory $outputFactory,
        private InstanceClient $instanceClient,
        private CommandActionRunner $commandActionRunner,
    ) {
        parent::__construct($instanceRepository, $outputFactory);
    }

    protected function getDefaultRetryLimit(): int
    {
        return self::DEFAULT_RETRY_LIMIT;
    }

    protected function getDefaultRetryDelay(): int
    {
        return self::DEFAULT_RETRY_DELAY;
    }

    protected function getRetryLimitOptionName(): string
    {
        return self::OPTION_RETRY_LIMIT;
    }

    protected function getRetryDelayOptionName(): string
    {
        return self::OPTION_RETRY_DELAY;
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parentExitCode = parent::execute($input, $output);
        if (Command::SUCCESS !== $parentExitCode) {
            return $parentExitCode;
        }

        $instance = $this->getInstance();

        $result = $this->commandActionRunner->run(
            $this->getRetryLimit($input),
            $this->getRetryDelay($input),
            $output,
            function (bool $isLastAttempt) use ($output, $instance): bool {
                $response = $this->instanceClient->getHealth($instance);
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
