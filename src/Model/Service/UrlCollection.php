<?php

declare(strict_types=1);

namespace App\Model\Service;

use App\Enum\UrlKey;

/**
 * @phpstan-type SerializedUrlCollection array{health_check_url: ?string, state_url: ?string}
 */
readonly class UrlCollection implements \JsonSerializable
{
    public function __construct(
        public ?string $healthCheckUrl,
        public ?string $stateUrl,
    ) {
    }

    /**
     * @return SerializedUrlCollection
     */
    public function jsonSerialize(): array
    {
        return [
            UrlKey::HEALTH_CHECK->value => $this->healthCheckUrl,
            UrlKey::STATE->value => $this->stateUrl,
        ];
    }
}
