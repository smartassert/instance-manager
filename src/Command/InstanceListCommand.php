<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\RequiredOptionMissingException;
use App\Services\CommandConfigurator;
use App\Services\InstanceRepository;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceListCommand::NAME,
    description: 'List instances',
)]
class InstanceListCommand extends AbstractServiceCommand
{
    public const NAME = 'app:instance:list';

    public function __construct(
        CommandConfigurator $configurator,
        private InstanceRepository $instanceRepository,
    ) {
        parent::__construct($configurator);
    }

    /**
     * @throws ExceptionInterface
     * @throws RequiredOptionMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);
        $output->write((string) json_encode(
            $this->instanceRepository->findAll($serviceId)
        ));

        return Command::SUCCESS;
    }
}
