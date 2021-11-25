<?php

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
    public function testCreate(array $data, ServiceConfiguration $expectedConfiguration): void
    {
        self::assertEquals($expectedConfiguration, ServiceConfiguration::create($data));
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        return [
            'empty' => [
                'data' => [],
                'expectedConfiguration' => new ServiceConfiguration(null, null)
            ],
            'invalid health check url, not a string' => [
                'data' => [
                    ServiceConfiguration::KEY_HEALTH_CHECK_URL => true,
                ],
                'expectedConfiguration' => new ServiceConfiguration(null, null)
            ],
            'invalid state url, not a string' => [
                'data' => [
                    ServiceConfiguration::KEY_STATE_URL => true,
                ],
                'expectedConfiguration' => new ServiceConfiguration(null, null)
            ],
            'health check url valid, state url invalid' => [
                'data' => [
                    ServiceConfiguration::KEY_HEALTH_CHECK_URL => '/health-check',
                    ServiceConfiguration::KEY_STATE_URL => true,
                ],
                'expectedConfiguration' => new ServiceConfiguration('/health-check', null)
            ],
            'health check url invalid, state url valid' => [
                'data' => [
                    ServiceConfiguration::KEY_HEALTH_CHECK_URL => true,
                    ServiceConfiguration::KEY_STATE_URL => '/',
                ],
                'expectedConfiguration' => new ServiceConfiguration(null, '/')
            ],
            'valid' => [
                'data' => [
                    ServiceConfiguration::KEY_HEALTH_CHECK_URL => '/health-check',
                    ServiceConfiguration::KEY_STATE_URL => '/',
                ],
                'expectedConfiguration' => new ServiceConfiguration('/health-check', '/')
            ],
        ];
    }
}
