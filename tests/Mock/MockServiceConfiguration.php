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
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('exists')
            ->with($serviceId)
            ->andReturn($exists)
        ;

        return $this;
    }

    public function withGetImageIdCall(string $serviceId, ?string $imageId): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getImageId')
            ->with($serviceId)
            ->andReturn($imageId)
        ;

        return $this;
    }

    public function withGetHealthCheckUrlCall(string $serviceId, ?string $healthCheckUrl): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getHealthCheckUrl')
            ->with($serviceId)
            ->andReturn($healthCheckUrl)
        ;

        return $this;
    }

    public function withGetStateUrlCall(string $serviceId, ?string $stateUrl): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getStateUrl')
            ->with($serviceId)
            ->andReturn($stateUrl)
        ;

        return $this;
    }
}
