<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\ServiceIdMissingException;
use App\Model\AssignedIp;
use App\Services\CommandConfigurator;
use App\Services\FloatingIpRepository;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: IpGetCommand::NAME,
    description: 'Get the current floating IP',
)]
class IpGetCommand extends AbstractServiceCommand
{
    public const NAME = 'app:ip:get';

    public function __construct(
        CommandConfigurator $configurator,
        private FloatingIpRepository $floatingIpRepository,
    ) {
        parent::__construct($configurator);
    }

    /**
     * @throws ExceptionInterface
     * @throws ServiceIdMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);

        $assignedIp = $this->floatingIpRepository->find($serviceId);
        if ($assignedIp instanceof AssignedIp) {
            $output->write($assignedIp->getIp());
        }

        return Command::SUCCESS;
    }
}
