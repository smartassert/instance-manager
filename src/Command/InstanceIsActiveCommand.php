<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Instance;
use App\Services\CommandActionRunner;
use App\Services\CommandConfigurator;
use App\Services\CommandInstanceRepository;
use App\Services\InstanceRepository;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceIsActiveCommand::NAME,
    description: 'Check if an instance is active',
)]
class InstanceIsActiveCommand extends Command
{
    use RetryableCommandTrait;

    public const NAME = 'app:instance:is-active';

    public const OPTION_RETRY_LIMIT = 'retry-limit';
    public const OPTION_RETRY_DELAY = 'retry-delay';
    public const DEFAULT_RETRY_LIMIT = 5;
    public const DEFAULT_RETRY_DELAY = 30;

    public function __construct(
        private CommandConfigurator $configurator,
        private CommandInstanceRepository $commandInstanceRepository,
        private InstanceRepository $instanceRepository,
        private CommandActionRunner $commandActionRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
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
            function (bool $isLastAttempt) use ($output, &$instance): bool {
                $dropletStatus = $instance instanceof Instance
                    ? $instance->getDropletStatus()
                    : Instance::DROPLET_STATUS_UNKNOWN;

                $output->write($dropletStatus);

                $isActive = Instance::DROPLET_STATUS_ACTIVE === $dropletStatus;

                if (false === $isActive && false === $isLastAttempt) {
                    $output->writeln('');
                    $instance = $this->instanceRepository->find((int) $this->commandInstanceRepository->getId());
                }

                return $isActive;
            }
        );

        return true === $result ? Command::SUCCESS : Command::FAILURE;
    }
}
