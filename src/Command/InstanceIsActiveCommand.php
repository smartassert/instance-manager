<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Instance;
use App\Services\CommandConfigurator;
use App\Services\CommandInstanceRepository;
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
    public const NAME = 'app:instance:is-active';

    public function __construct(
        private CommandConfigurator $configurator,
        private CommandInstanceRepository $commandInstanceRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator->addId($this);
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

        $output->write(Instance::DROPLET_STATUS_ACTIVE === $instance->getDropletStatus() ? 'true' : 'false');

        return Command::SUCCESS;
    }
}
