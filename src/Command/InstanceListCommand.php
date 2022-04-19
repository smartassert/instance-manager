<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Filter;
use App\Model\FilterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(
    name: InstanceListCommand::NAME,
    description: 'List instances',
)]
class InstanceListCommand extends AbstractInstanceListCommand
{
    public const NAME = 'app:instance:list';

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
                $this->filterFactory->createFromString($negativeFilterString, FilterInterface::MATCH_TYPE_NEGATIVE)
            );
        }

        $positiveFilterString = $input->getOption(self::OPTION_INCLUDE);
        if (is_string($positiveFilterString)) {
            $filters = array_merge(
                $filters,
                $this->filterFactory->createFromString($positiveFilterString, FilterInterface::MATCH_TYPE_POSITIVE)
            );
        }

        return $filters;
    }
}
