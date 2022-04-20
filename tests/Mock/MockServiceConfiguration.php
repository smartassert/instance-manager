<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use App\Services\ServiceConfiguration;
use Mockery\MockInterface;

class MockServiceConfiguration
{
    private ServiceConfiguration $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(ServiceConfiguration::class);
    }

    public function getMock(): ServiceConfiguration
    {
        return $this->mock;
    }

    public function withExistsCall(string $serviceId, bool $exists): self
    {
        return $this->withCall($serviceId, 'exists', $exists);
    }

    public function withGetImageIdCall(string $serviceId, ?string $imageId): self
    {
        return $this->withCall($serviceId, 'getImageId', $imageId);
    }

    public function withGetHealthCheckUrlCall(string $serviceId, ?string $healthCheckUrl): self
    {
        return $this->withCall($serviceId, 'getHealthCheckUrl', $healthCheckUrl);
    }

    public function withGetStateUrlCall(string $serviceId, ?string $stateUrl): self
    {
        return $this->withCall($serviceId, 'getStateUrl', $stateUrl);
    }

    private function withCall(string $serviceId, string $method, mixed $return): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive($method)
            ->with($serviceId)
            ->andReturn($return)
        ;

        return $this;
    }
}
