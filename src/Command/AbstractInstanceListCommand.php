<?php

namespace App\Command;

use App\Model\Filter;
use App\Model\InstanceCollection;
use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\InstanceCollectionHydrator;
use App\Services\InstanceRepository;
use App\Services\ServiceConfiguration;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractInstanceListCommand extends Command
{
    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 3;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private InstanceCollectionHydrator $instanceCollectionHydrator,
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
        private ServiceConfiguration $serviceConfiguration,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator->addServiceIdOption($this);
    }

    /**
     * @return Filter[]
     */
    abstract protected function createFilterCollection(InputInterface $input): array;

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

        $output->write((string) json_encode($this->findInstances(
            $serviceId,
            $this->createFilterCollection($input)
        )));

        return Command::SUCCESS;
    }

    /**
     * @param Filter[] $filters
     *
     * @throws ExceptionInterface
     */
    private function findInstances(string $serviceId, array $filters): InstanceCollection
    {
        $stateUrl = (string) $this->serviceConfiguration->getServiceConfiguration($serviceId)?->getStateUrl();
        $instances = $this->instanceRepository->findAll($serviceId);
        $instances = $this->instanceCollectionHydrator->hydrate($instances, $stateUrl);

        foreach ($filters as $filter) {
            $instances = $instances->filterByFilter($filter);
        }

        return $instances;
    }
}
