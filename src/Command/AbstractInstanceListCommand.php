<?php

namespace App\Command;

use App\Model\Filter;
use App\Model\InstanceCollection;
use App\Services\InstanceCollectionHydrator;
use App\Services\InstanceRepository;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractInstanceListCommand extends Command
{
    public function __construct(
        private InstanceRepository $instanceRepository,
        private InstanceCollectionHydrator $instanceCollectionHydrator,
    ) {
        parent::__construct(null);
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
        $output->write((string) json_encode($this->findInstances(
            $this->createFilterCollection($input)
        )));

        return Command::SUCCESS;
    }

    /**
     * @param Filter[] $filters
     *
     * @throws ExceptionInterface
     */
    private function findInstances(array $filters): InstanceCollection
    {
        $instances = $this->instanceRepository->findAll();
        $instances = $this->instanceCollectionHydrator->hydrate($instances);

        foreach ($filters as $filter) {
            $instances = $instances->filter($filter);
        }

        return $instances;
    }
}
