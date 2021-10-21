<?php

namespace App\Command;

use App\Model\Filter;
use App\Services\FilterFactory;
use App\Services\InstanceCollectionHydrator;
use App\Services\InstanceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

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
     * @return Filter[]
     */
    protected function createFilterCollection(InputInterface $input): array
    {
        $filters = [];

        $negativeFilterString = $input->getOption(self::OPTION_EXCLUDE);
        if (is_string($negativeFilterString)) {
            $filters = array_merge(
                $filters,
                $this->filterFactory->createNegativeFiltersFromString($negativeFilterString)
            );
        }

        $positiveFilterString = $input->getOption(self::OPTION_INCLUDE);
        if (is_string($positiveFilterString)) {
            $filters = array_merge(
                $filters,
                $this->filterFactory->createPositiveFiltersFromString($positiveFilterString)
            );
        }

        return $filters;
    }
}
