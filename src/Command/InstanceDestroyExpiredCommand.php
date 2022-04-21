<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\RequiredOptionMissingException;
use App\Model\Instance;
use App\Model\InstanceCollection;
use App\Services\CommandConfigurator;
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
class InstanceDestroyExpiredCommand extends AbstractServiceCommand
{
    public const NAME = 'app:instance:destroy-expired';

    public function __construct(
        CommandConfigurator $configurator,
        private OutputFactory $outputFactory,
        private FloatingIpRepository $floatingIpRepository,
        private InstanceRepository $instanceRepository,
    ) {
        parent::__construct($configurator);
    }

    /**
     * @throws ExceptionInterface
     * @throws RequiredOptionMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);
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
