<?php

declare(strict_types=1);

namespace App\Services;

use DigitalOceanV2\Api\Image as ImageApi;
use DigitalOceanV2\Exception\ExceptionInterface;
use DigitalOceanV2\Exception\RuntimeException;

class ImageRepository
{
    public function __construct(
        private ImageApi $imageApi,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function exists(int $imageId): bool
    {
        try {
            $this->imageApi->getById($imageId);

            return true;
        } catch (ExceptionInterface $exception) {
            $isNotFoundException = $exception instanceof RuntimeException && 404 === $exception->getCode();

            if (false === $isNotFoundException) {
                throw $exception;
            }
        }

        return false;
    }
}
