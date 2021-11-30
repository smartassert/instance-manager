<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Model\Instance;
use App\Model\ServiceConfiguration;
use App\Services\InstanceRouteGenerator;
use App\Tests\Services\DropletDataFactory;
use App\Tests\Services\InstanceFactory;
use PHPUnit\Framework\TestCase;

class InstanceRouteGeneratorTest extends TestCase
{
    private InstanceRouteGenerator $instanceRouteGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instanceRouteGenerator = new InstanceRouteGenerator();
    }

    /**
     * @dataProvider createHealthCheckUrlDataProvider
     */
    public function testCreateHealthCheckUrl(
        ServiceConfiguration $serviceConfiguration,
        Instance $instance,
        string $expectedUrl
    ): void {
        self::assertSame(
            $expectedUrl,
            $this->instanceRouteGenerator->createHealthCheckUrl($serviceConfiguration, $instance)
        );
    }

    /**
     * @return array<mixed>
     */
    public function createHealthCheckUrlDataProvider(): array
    {
        return [
            'no url, no IP address' => [
                'serviceConfiguration' => new ServiceConfiguration('service_id', '', ''),
                'instance' => InstanceFactory::create([
                    'id' => 123,
                ]),
                'expectedUrl' => '',
            ],
            'has url, no IP address' => [
                'serviceConfiguration' => new ServiceConfiguration(
                    'service_id',
                    'https://{{ host }}/health-check',
                    ''
                ),
                'instance' => InstanceFactory::create([
                    'id' => 123,
                ]),
                'expectedUrl' => '',
            ],
            'no url, has IP address' => [
                'serviceConfiguration' => new ServiceConfiguration('service_id', '', ''),
                'instance' => InstanceFactory::create(DropletDataFactory::createWithIps(123, [
                    '127.0.0.1',
                ])),
                'expectedUrl' => '',
            ],
            'has url, has IP address' => [
                'serviceConfiguration' => new ServiceConfiguration(
                    'service_id',
                    'https://{{ host }}/health-check',
                    ''
                ),
                'instance' => InstanceFactory::create(DropletDataFactory::createWithIps(123, [
                    '127.0.0.1',
                ])),
                'expectedUrl' => 'https://127.0.0.1/health-check',
            ],
        ];
    }

    /**
     * @dataProvider createStateUrlDataProvider
     */
    public function testCreateStateUrl(
        ServiceConfiguration $serviceConfiguration,
        Instance $instance,
        string $expectedUrl
    ): void {
        self::assertSame(
            $expectedUrl,
            $this->instanceRouteGenerator->createStateUrl($serviceConfiguration, $instance)
        );
    }

    /**
     * @return array<mixed>
     */
    public function createStateUrlDataProvider(): array
    {
        return [
            'no url, no IP address' => [
                'serviceConfiguration' => new ServiceConfiguration('service_id', '', ''),
                'instance' => InstanceFactory::create([
                    'id' => 123,
                ]),
                'expectedUrl' => '',
            ],
            'has url, no IP address' => [
                'serviceConfiguration' => new ServiceConfiguration(
                    'service_id',
                    '',
                    'https://{{ host }}/state'
                ),
                'instance' => InstanceFactory::create([
                    'id' => 123,
                ]),
                'expectedUrl' => '',
            ],
            'no url, has IP address' => [
                'serviceConfiguration' => new ServiceConfiguration(
                    'service_id',
                    '',
                    ''
                ),
                'instance' => InstanceFactory::create(DropletDataFactory::createWithIps(123, [
                    '127.0.0.2',
                ])),
                'expectedUrl' => '',
            ],
            'has url, has IP address' => [
                'serviceConfiguration' => new ServiceConfiguration(
                    'service_id',
                    '',
                    'https://{{ host }}/state'
                ),
                'instance' => InstanceFactory::create(DropletDataFactory::createWithIps(123, [
                    '127.0.0.2',
                ])),
                'expectedUrl' => 'https://127.0.0.2/state',
            ],
        ];
    }
}
