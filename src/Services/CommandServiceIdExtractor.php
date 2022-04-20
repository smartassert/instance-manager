<?php

declare(strict_types=1);

namespace App\Services;

use App\Command\Option;
use App\Exception\ServiceIdMissingException;
use Symfony\Component\Console\Input\InputInterface;

class CommandServiceIdExtractor
{
    /**
     * @throws ServiceIdMissingException
     */
    public function extract(InputInterface $input): string
    {
        $serviceId = $input->getOption(Option::OPTION_SERVICE_ID);
        $serviceId = is_string($serviceId) ? $serviceId : '';

        if ('' === $serviceId) {
            throw new ServiceIdMissingException();
        }

        return $serviceId;
    }
}
