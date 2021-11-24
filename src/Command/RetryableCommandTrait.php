<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;

trait RetryableCommandTrait
{
    public function getRetryLimit(InputInterface $input): int
    {
        $limit = $input->getOption(self::OPTION_RETRY_LIMIT);

        return is_numeric($limit) ? (int) $limit : self::DEFAULT_RETRY_LIMIT;
    }

    public function getRetryDelay(InputInterface $input): int
    {
        $delay = $input->getOption(self::OPTION_RETRY_DELAY);

        return is_numeric($delay) ? (int) $delay : self::DEFAULT_RETRY_DELAY;
    }
}
