<?php

declare(strict_types=1);

namespace App\Model;

class ServiceConfiguration
{
    public const KEY_HEALTH_CHECK_URL = 'health_check_url';
    public const KEY_STATE_URL = 'state_url';

    public function __construct(
        private string $serviceId,
        private ?string $healthCheckUrl,
        private ?string $stateUrl,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function create(string $serviceId, array $data): self
    {
        $healthCheckUrl = $data[self::KEY_HEALTH_CHECK_URL] ?? null;
        $healthCheckUrl = is_string($healthCheckUrl) ? $healthCheckUrl : null;

        $stateUrl = $data[self::KEY_STATE_URL] ?? null;
        $stateUrl = is_string($stateUrl) ? $stateUrl : null;

        return new ServiceConfiguration($serviceId, $healthCheckUrl, $stateUrl);
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function getHealthCheckUrl(): ?string
    {
        return $this->healthCheckUrl;
    }

    public function getStateUrl(): ?string
    {
        return $this->stateUrl;
    }
}
