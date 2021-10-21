<?php

namespace App\Command;

use App\Model\Filter;
use App\Model\FilterInterface;
use App\Model\InstanceCollection;
use App\Services\FilterFactory;
use App\Services\InstanceCollectionHydrator;
use App\Services\InstanceRepository;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceListDestroyableCommand::NAME,
    description: 'List destroyable instances',
)]
class InstanceListDestroyableCommand extends Command
{
    public const NAME = 'app:instance:list-destroyable';
    public const OPTION_EXCLUDED_IP = 'excluded-ip';

    public function __construct(
        private InstanceRepository $instanceRepository,
        private InstanceCollectionHydrator $instanceCollectionHydrator,
        private FilterFactory $filterFactory,
    ) {
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPTION_EXCLUDED_IP,
                null,
                InputOption::VALUE_REQUIRED,
                'Exclude instances with given IP'
            )
        ;
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $excludedIp = $input->getOption(self::OPTION_EXCLUDED_IP);
        if (!is_string($excludedIp)) {
            $excludedIp = '';
        }

        $filters = [
            new Filter('idle', true, FilterInterface::MATCH_TYPE_POSITIVE),
            new Filter('ips', $excludedIp, FilterInterface::MATCH_TYPE_NEGATIVE),
        ];

        $output->write((string) json_encode($this->findInstances($filters)));

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

    /**
     * @param FilterInterface::MATCH_TYPE_* $matchType
     *
     * @return Filter[]
     */
    private function createFilterCollection(InputInterface $input, string $optionName, string $matchType): array
    {
        $filterString = $input->getOption($optionName);
        if (!is_string($filterString)) {
            return [];
        }

        return FilterInterface::MATCH_TYPE_NEGATIVE === $matchType
            ? $this->filterFactory->createNegativeFiltersFromString($filterString)
            : $this->filterFactory->createPositiveFiltersFromString($filterString);
    }
}
