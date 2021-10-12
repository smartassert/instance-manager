<?php

namespace App\Model;

use DigitalOceanV2\Exception\ExceptionInterface;
use DigitalOceanV2\Exception\RuntimeException;

class ExcludeNotFoundDropletAction implements DropletActionInterface
{
    /**
     * @param \Closure(int $id): mixed $action
     */
    public function __construct(
        private \Closure $action,
    ) {
    }

    public function __invoke(int $id): mixed
    {
        try {
            return ($this->action)($id);
        } catch (ExceptionInterface $exception) {
            if ($exception instanceof RuntimeException && 404 === $exception->getCode()) {
                return null;
            }

            throw $exception;
        }
    }
}
