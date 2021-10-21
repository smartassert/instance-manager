<?php

namespace App\Command;

use App\Model\Filter;
use App\Model\FilterInterface;
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
    name: InstanceListCommand::NAME,
    description: 'List instances',
)]
class InstanceListCommand extends AbstractInstanceListCommand
{
    public const NAME = 'app:instance:list';
    public const OPTION_INCLUDE = 'include';
    public const OPTION_EXCLUDE = 'exclude';

    public function __construct(
        InstanceRepository $instanceRepository,
        InstanceCollectionHydrator $instanceCollectionHydrator,
        private FilterFactory $filterFactory,
    ) {
        parent::__construct($instanceRepository, $instanceCollectionHydrator);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPTION_INCLUDE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Include instances matching this filter'
            )
            ->addOption(
                self::OPTION_EXCLUDE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Exclude instances matching this filter'
            )
        ;
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filters = array_merge(
            $this->createFilterCollection($input, self::OPTION_INCLUDE, FilterInterface::MATCH_TYPE_POSITIVE),
            $this->createFilterCollection($input, self::OPTION_EXCLUDE, FilterInterface::MATCH_TYPE_NEGATIVE)
        );

        $output->write((string) json_encode($this->findInstances($filters)));

        return Command::SUCCESS;
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
