<?php

namespace App\Command;

use App\Model\Filter;
use App\Model\InstanceCollection;
use App\Services\InstanceCollectionHydrator;
use App\Services\InstanceRepository;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractInstanceListCommand extends Command
{
    public const OPTION_COLLECTION_TAG = 'collection-tag';
    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 3;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private InstanceCollectionHydrator $instanceCollectionHydrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPTION_COLLECTION_TAG,
                null,
                InputOption::VALUE_REQUIRED,
                'Tag applied to all instances'
            )
        ;
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
        $collectionTag = $input->getOption(self::OPTION_COLLECTION_TAG);
        $collectionTag = trim(is_string($collectionTag) ? $collectionTag : '');
        if ('' === $collectionTag) {
            $output->writeln('"' . self::OPTION_COLLECTION_TAG . '" option empty');

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
