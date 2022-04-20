<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\ServiceIdMissingException;
use App\Model\AssignedIp;
use App\Services\CommandConfigurator;
use App\Services\CommandServiceIdExtractor;
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
class IpGetCommand extends Command
{
    public const NAME = 'app:ip:get';
    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 3;

    public function __construct(
        private CommandConfigurator $configurator,
        private CommandServiceIdExtractor $serviceIdExtractor,
        private FloatingIpRepository $floatingIpRepository,
    ) {
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this->configurator->addServiceIdOption($this);
    }

    /**
     * @throws ExceptionInterface
     * @throws ServiceIdMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->serviceIdExtractor->extract($input);

        $assignedIp = $this->floatingIpRepository->find($serviceId);
        if ($assignedIp instanceof AssignedIp) {
            $output->write($assignedIp->getIp());
        }

        return Command::SUCCESS;
    }
}
