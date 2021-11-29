<?php

declare(strict_types=1);

namespace App\Services;

use DigitalOceanV2\Api\Action as ActionApi;
use DigitalOceanV2\Entity\Action as ActionEntity;
use DigitalOceanV2\Exception\ExceptionInterface;
use DigitalOceanV2\Exception\RuntimeException;

class ActionRepository
{
    public function __construct(
        private ActionApi $actionApi,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function find(int $id): ?ActionEntity
    {
        try {
            return $this->actionApi->getById($id);
        } catch (ExceptionInterface $exception) {
            if ($exception instanceof RuntimeException && 404 === $exception->getCode()) {
                return null;
            }

            throw $exception;
        }
    }

    public function update(ActionEntity $actionEntity): ?ActionEntity
    {
        return $this->find($actionEntity->id);
    }
}
