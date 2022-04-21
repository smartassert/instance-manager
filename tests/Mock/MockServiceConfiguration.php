<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use App\Model\EnvironmentVariable;
use App\Services\ServiceConfiguration;
use Doctrine\Common\Collections\Collection;
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

    public function withGetImageIdCall(string $serviceId, null|string|\Exception $outcome): self
    {
        return $this->withCall($serviceId, 'getImageId', $outcome);
    }

    public function withGetHealthCheckUrlCall(string $serviceId, null|string|\Exception $outcome): self
    {
        return $this->withCall($serviceId, 'getHealthCheckUrl', $outcome);
    }

    public function withGetStateUrlCall(string $serviceId, null|string|\Exception $outcome): self
    {
        return $this->withCall($serviceId, 'getStateUrl', $outcome);
    }

    public function withGetDomainCall(string $serviceId, null|string|\Exception $outcome): self
    {
        return $this->withCall($serviceId, 'getDomain', $outcome);
    }

    /**
     * @param Collection<int, EnvironmentVariable>|\Exception $outcome
     */
    public function withGetEnvironmentVariablesCall(string $serviceId, Collection|\Exception $outcome): self
    {
        return $this->withCall($serviceId, 'getEnvironmentVariables', $outcome);
    }

    private function withCall(string $serviceId, string $method, mixed $outcome): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $expectation = $this->mock
            ->shouldReceive($method)
            ->with($serviceId)
        ;

        if ($outcome instanceof \Exception) {
            $expectation->andThrow($outcome);
        } else {
            $expectation->andReturn($outcome);
        }

        return $this;
    }
}
