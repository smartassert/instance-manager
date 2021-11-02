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
    name: InstanceIsPostCreateCompleteCommand::NAME,
    description: 'Check if the post-create steps of an instance are complete',
)]
class InstanceIsPostCreateCompleteCommand extends AbstractInstanceActionCommand
{
    public const NAME = 'app:instance:is-post-create-complete';
    public const EXIT_CODE_ID_INVALID = 3;
    public const EXIT_CODE_NOT_FOUND = 4;

    public const OPTION_RETRY_LIMIT = 'retry-limit';
    public const OPTION_RETRY_DELAY = 'retry-delay';
    public const DEFAULT_RETRY_LIMIT = 5;
    public const DEFAULT_RETRY_DELAY = 30;
    private const MICROSECONDS_PER_SECOND = 1000000;

    public function __construct(
        InstanceRepository $instanceRepository,
        private InstanceClient $instanceClient,
        private OutputFactory $outputFactory,
        private CommandActionRunner $commandActionRunner,
    ) {
        parent::__construct($instanceRepository);
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
        parent::execute($input, $output);

        $id = $this->getId();
        if (null === $id) {
            $output->write($this->outputFactory->createErrorOutput('id-invalid'));

            return self::EXIT_CODE_ID_INVALID;
        }

        $instance = $this->instanceRepository->find($id);
        if (null === $instance) {
            $output->write($this->outputFactory->createErrorOutput('not-found', ['id' => $id]));

            return self::EXIT_CODE_NOT_FOUND;
        }

        $limit = $input->getOption(self::OPTION_RETRY_LIMIT);
        $limit = is_numeric($limit) ? (int) $limit : self::DEFAULT_RETRY_LIMIT;

        $delay = $input->getOption(self::OPTION_RETRY_DELAY);
        $delay = is_numeric($delay) ? (int) $delay : self::DEFAULT_RETRY_DELAY;

        $result = $this->commandActionRunner->run(
            $limit,
            $delay,
            $output,
            function (bool $isLastAttempt) use ($output, $instance): bool {
                $state = $this->instanceClient->getState($instance);

                $postCreateCompleteState = $state['post-create-complete'] ?? null;
                $postCreateCompleteState = is_bool($postCreateCompleteState) ? $postCreateCompleteState : true;

                $output->write($postCreateCompleteState ? 'complete' : 'not-complete');

                if (false === $postCreateCompleteState && false === $isLastAttempt) {
                    $output->writeln('');
                }

                return $postCreateCompleteState;
            }
        );

        return true === $result ? Command::SUCCESS : Command::FAILURE;
    }
}
