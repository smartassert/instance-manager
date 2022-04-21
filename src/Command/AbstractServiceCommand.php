<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\RequiredOptionMissingException;
use App\Services\CommandConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

abstract class AbstractServiceCommand extends Command
{
    public function __construct(
        protected CommandConfigurator $configurator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator->addServiceIdOption($this);
    }

    /**
     * @throws RequiredOptionMissingException
     */
    protected function getServiceId(InputInterface $input): string
    {
        $serviceId = $input->getOption(Option::OPTION_SERVICE_ID);
        $serviceId = is_string($serviceId) ? $serviceId : '';

        if ('' === $serviceId) {
            throw new RequiredOptionMissingException(Option::OPTION_SERVICE_ID);
        }

        return $serviceId;
    }
}
