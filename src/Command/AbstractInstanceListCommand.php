<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Filter;
use App\Model\InstanceCollection;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;
use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\FilterFactory;
use App\Services\InstanceCollectionHydrator;
use App\Services\InstanceRepository;
use App\Services\ServiceConfiguration;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractInstanceListCommand extends Command
{
    public const EXIT_CODE_EMPTY_SERVICE_ID = 5;
    public const EXIT_CODE_SERVICE_CONFIGURATION_MISSING = 6;
    public const EXIT_CODE_SERVICE_STATE_URL_MISSING = 7;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private InstanceCollectionHydrator $instanceCollectionHydrator,
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
        private ServiceConfiguration $serviceConfiguration,
        protected FilterFactory $filterFactory,
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
            $output->write('"' . Option::OPTION_SERVICE_ID . '" option empty');

            return self::EXIT_CODE_EMPTY_SERVICE_ID;
        }

        $serviceConfiguration = $this->serviceConfiguration->getServiceConfiguration($serviceId);
        if (null === $serviceConfiguration) {
            $output->write('No configuration for service "' . $serviceId . '"');

            return self::EXIT_CODE_SERVICE_CONFIGURATION_MISSING;
        }

        if ('' === $serviceConfiguration->getStateUrl()) {
            $output->write('No state_url for service "' . $serviceId . '"');

            return self::EXIT_CODE_SERVICE_STATE_URL_MISSING;
        }

        $output->write((string) json_encode($this->findInstances(
            $serviceConfiguration,
            $this->createFilterCollection($input)
        )));

        return Command::SUCCESS;
    }

    /**
     * @param Filter[] $filters
     *
     * @throws ExceptionInterface
     */
    private function findInstances(ServiceConfigurationModel $serviceConfiguration, array $filters): InstanceCollection
    {
        $instances = $this->instanceRepository->findAll($serviceConfiguration->getServiceId());
        $instances = $this->instanceCollectionHydrator->hydrate($serviceConfiguration, $instances);

        foreach ($filters as $filter) {
            $instances = $instances->filterByFilter($filter);
        }

        return $instances;
    }
}
