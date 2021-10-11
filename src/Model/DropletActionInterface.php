<?php

namespace App\Model;

use DigitalOceanV2\Exception\ExceptionInterface;

interface DropletActionInterface
{
    /**
     * @throws ExceptionInterface
     */
    public function __invoke(int $id): mixed;
}
