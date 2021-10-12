<?php

namespace App\Command;

use App\Model\AssignedIp;
use App\Services\FloatingIpRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: IpGetCommand::NAME,
    description: 'Get the current floating IP',
)]
class IpGetCommand extends Command
{
    public const NAME = 'app:ip:get';

    public function __construct(
        private FloatingIpRepository $floatingIpRepository,
    ) {
        parent::__construct(null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $assignedIp = $this->floatingIpRepository->find();
        if ($assignedIp instanceof AssignedIp) {
            $output->write($assignedIp->getIp());
        }

        return Command::SUCCESS;
    }
}
