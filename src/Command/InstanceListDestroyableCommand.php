<?php

namespace App\Command;

use App\Model\Filter;
use App\Model\FilterInterface;
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
class InstanceListDestroyableCommand extends AbstractInstanceListCommand
{
    public const NAME = 'app:instance:list-destroyable';
    public const OPTION_EXCLUDED_IP = 'excluded-ip';

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
}
