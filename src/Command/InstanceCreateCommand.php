<?php

namespace App\Command;

use App\Services\InstanceRepository;
use App\Services\OutputFactory;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceCreateCommand::NAME,
    description: 'Create an instance',
)]
class InstanceCreateCommand extends Command
{
    public const NAME = 'app:instance:create';

    public function __construct(
        private InstanceRepository $instanceRepository,
        private OutputFactory $outputFactory,
    ) {
        parent::__construct(null);
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $instance = $this->instanceRepository->findCurrent();
        if (null === $instance) {
            $instance = $this->instanceRepository->create();
        }

        $output->write($this->outputFactory->createSuccessOutput(['id' => $instance->getId()]));

        return Command::SUCCESS;
    }
}
