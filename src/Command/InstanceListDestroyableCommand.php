<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Filter;
use App\Model\FilterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: InstanceListDestroyableCommand::NAME,
    description: 'List destroyable instances',
)]
class InstanceListDestroyableCommand extends AbstractInstanceListCommand
{
    public const NAME = 'app:instance:list-destroyable';
    public const OPTION_EXCLUDED_IP = 'excluded-ip';

    protected function configure(): void
    {
        parent::configure();

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
     * @return Filter[]
     */
    protected function createFilterCollection(InputInterface $input): array
    {
        $excludedIp = $input->getOption(self::OPTION_EXCLUDED_IP);
        if (!is_string($excludedIp)) {
            $excludedIp = '';
        }

        return [
            new Filter('idle', true, FilterInterface::MATCH_TYPE_POSITIVE),
            new Filter('ips', $excludedIp, FilterInterface::MATCH_TYPE_NEGATIVE),
        ];
    }
}
