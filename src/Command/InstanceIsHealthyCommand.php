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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceIsHealthyCommand::NAME,
    description: 'Perform instance health check',
)]
class InstanceIsHealthyCommand extends AbstractInstanceObjectCommand
{
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

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                self::OPTION_RETRY_LIMIT,
                null,
                InputOption::VALUE_REQUIRED,
                'How many times to retry if post-create actions are not complete?',
                self::DEFAULT_RETRY_LIMIT
            )
            ->addOption(
                self::OPTION_RETRY_DELAY,
                null,
                InputOption::VALUE_REQUIRED,
                'How long to wait, in seconds, if post-create actions are not complete?',
                self::DEFAULT_RETRY_DELAY
            )
        ;
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

        $limit = $input->getOption(self::OPTION_RETRY_LIMIT);
        $limit = is_numeric($limit) ? (int) $limit : self::DEFAULT_RETRY_LIMIT;

        $delay = $input->getOption(self::OPTION_RETRY_DELAY);
        $delay = is_numeric($delay) ? (int) $delay : self::DEFAULT_RETRY_DELAY;

        $result = $this->commandActionRunner->run(
            $limit,
            $delay,
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
