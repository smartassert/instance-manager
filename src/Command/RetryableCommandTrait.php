<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

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

    abstract protected function getDefaultRetryLimit(): int;

    abstract protected function getDefaultRetryDelay(): int;

    abstract protected function getRetryLimitOptionName(): string;

    abstract protected function getRetryDelayOptionName(): string;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                $this->getRetryLimitOptionName(),
                null,
                InputOption::VALUE_REQUIRED,
                'How many times to retry command is not successful',
                $this->getDefaultRetryLimit()
            )
            ->addOption(
                $this->getRetryDelayOptionName(),
                null,
                InputOption::VALUE_REQUIRED,
                'How long to wait, in seconds, if command is not successful',
                $this->getDefaultRetryDelay()
            )
        ;
    }
}
