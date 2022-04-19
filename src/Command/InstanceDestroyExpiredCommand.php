<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Instance;
use App\Model\InstanceCollection;
use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\FloatingIpRepository;
use App\Services\InstanceRepository;
use App\Services\OutputFactory;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceDestroyExpiredCommand::NAME,
    description: 'Destroy all instances that have expired',
)]
class InstanceDestroyExpiredCommand extends Command
{
    public const NAME = 'app:instance:destroy-expired';
    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 3;

    public function __construct(
        private OutputFactory $outputFactory,
        private FloatingIpRepository $floatingIpRepository,
        private InstanceRepository $instanceRepository,
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator->addServiceIdOption($this);
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->inputReader->getTrimmedStringOption(Option::OPTION_SERVICE_ID, $input);
        if ('' === $serviceId) {
            $output->writeln('"' . Option::OPTION_SERVICE_ID . '" option empty');

            return self::EXIT_CODE_EMPTY_COLLECTION_TAG;
        }

        $instances = $this->instanceRepository->findAll($serviceId);
        if (1 >= count($instances)) {
            return Command::SUCCESS;
        }

        $assignedIp = $this->floatingIpRepository->find($serviceId);
        if (null === $assignedIp) {
            $output->write($this->outputFactory->createErrorOutput('no-ip'));

            return Command::SUCCESS;
        }

        $expiredInstances = $this->filterToInstancesOlderThan($instances, $assignedIp->getInstance()->getCreatedAt());

        if (count($expiredInstances) > 0) {
            $output->write((string) json_encode($expiredInstances));

            /** @var Instance $instance */
            foreach ($expiredInstances as $instance) {
                $this->instanceRepository->delete($instance->getId());
            }
        }

        return Command::SUCCESS;
    }

    private function filterToInstancesOlderThan(InstanceCollection $collection, string $threshold): InstanceCollection
    {
        $filteredInstances = [];

        /** @var Instance $instance */
        foreach ($collection as $instance) {
            if ($instance->getCreatedAt() < $threshold) {
                $filteredInstances[] = $instance;
            }
        }

        return new InstanceCollection($filteredInstances);
    }
}
