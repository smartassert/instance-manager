<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\ServiceConfiguration;
use PHPUnit\Framework\TestCase;

class ServiceConfigurationTest extends TestCase
{
    /**
     * @dataProvider createDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreate(string $serviceId, array $data, ServiceConfiguration $expectedConfiguration): void
    {
        self::assertEquals($expectedConfiguration, ServiceConfiguration::create($serviceId, $data));
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $serviceId = 'service_id';

        return [
            'empty' => [
                'serviceId' => $serviceId,
                'data' => [],
                'expectedConfiguration' => new ServiceConfiguration($serviceId, null, null)
            ],
            'invalid health check url, not a string' => [
                'serviceId' => $serviceId,
                'data' => [
                    ServiceConfiguration::KEY_HEALTH_CHECK_URL => true,
                ],
                'expectedConfiguration' => new ServiceConfiguration($serviceId, null, null)
            ],
            'invalid state url, not a string' => [
                'serviceId' => $serviceId,
                'data' => [
                    ServiceConfiguration::KEY_STATE_URL => true,
                ],
                'expectedConfiguration' => new ServiceConfiguration($serviceId, null, null)
            ],
            'health check url valid, state url invalid' => [
                'serviceId' => $serviceId,
                'data' => [
                    ServiceConfiguration::KEY_HEALTH_CHECK_URL => '/health-check',
                    ServiceConfiguration::KEY_STATE_URL => true,
                ],
                'expectedConfiguration' => new ServiceConfiguration($serviceId, '/health-check', null)
            ],
            'health check url invalid, state url valid' => [
                'serviceId' => $serviceId,
                'data' => [
                    ServiceConfiguration::KEY_HEALTH_CHECK_URL => true,
                    ServiceConfiguration::KEY_STATE_URL => '/',
                ],
                'expectedConfiguration' => new ServiceConfiguration($serviceId, null, '/')
            ],
            'valid' => [
                'serviceId' => $serviceId,
                'data' => [
                    ServiceConfiguration::KEY_HEALTH_CHECK_URL => '/health-check',
                    ServiceConfiguration::KEY_STATE_URL => '/',
                ],
                'expectedConfiguration' => new ServiceConfiguration($serviceId, '/health-check', '/')
            ],
        ];
    }
}
