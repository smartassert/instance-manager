<?php

declare(strict_types=1);

namespace App\Model\Service;

readonly class UrlCollection
{
    public function __construct(
        public ?string $healthCheckUrl,
        public ?string $stateUrl,
    ) {
    }
}
