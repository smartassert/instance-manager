<?php

namespace App\Command;

use App\Model\Filter;
use App\Model\InstanceCollection;
use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\InstanceCollectionHydrator;
use App\Services\InstanceRepository;
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator->addCollectionTagOption($this);
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
        $collectionTag = $this->inputReader->getTrimmedStringOption(CommandConfigurator::OPTION_COLLECTION_TAG, $input);
        if ('' === $collectionTag) {
            $output->writeln('"' . CommandConfigurator::OPTION_COLLECTION_TAG . '" option empty');

            return self::EXIT_CODE_EMPTY_COLLECTION_TAG;
        }

        $output->write((string) json_encode($this->findInstances(
            $collectionTag,
            $this->createFilterCollection($input)
        )));

        return Command::SUCCESS;
    }

    /**
     * @param Filter[] $filters
     *
     * @throws ExceptionInterface
     */
    private function findInstances(string $collectionTag, array $filters): InstanceCollection
    {
        $instances = $this->instanceRepository->findAll($collectionTag);
        $instances = $this->instanceCollectionHydrator->hydrate($instances);

        foreach ($filters as $filter) {
            $instances = $instances->filter($filter);
        }

        return $instances;
    }
}
