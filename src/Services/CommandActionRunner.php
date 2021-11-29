<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Console\Output\OutputInterface;

class CommandActionRunner
{
    private const MICROSECONDS_PER_SECOND = 1000000;

    /**
     * @param callable(bool $isLastAttempt): bool $action
     */
    public function run(int $limit, int $delay, OutputInterface $output, callable $action): bool
    {
        $count = 0;
        do {
            try {
                $result = ($action)($count === $limit - 1);
            } catch (\Exception $exception) {
                $output->write($exception->getMessage());
                $result = false;
            }

            if (false === $result) {
                usleep($delay * self::MICROSECONDS_PER_SECOND);
                ++$count;
            }
        } while ($count < $limit && false === $result);

        return $result;
    }
}
