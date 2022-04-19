<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Instance;
use App\Tests\Services\DropletDataFactory;
use App\Tests\Services\InstanceFactory;
use PHPUnit\Framework\TestCase;

class InstanceTest extends TestCase
{
    /**
     * @dataProvider hasIpDataProvider
     */
    public function testHasIp(Instance $instance, string $ip, bool $expectedHas): void
    {
        self::assertSame($expectedHas, $instance->hasIp($ip));
    }

    /**
     * @return array<mixed>
     */
    public function hasIpDataProvider(): array
    {
        return [
            'no IPs' => [
                'instance' => InstanceFactory::create([
                    'id' => 123,
                ]),
                'ip' => '127.0.0.1',
                'expectedHas' => false,
            ],
            'no matching IP' => [
                'instance' => InstanceFactory::create(
                    DropletDataFactory::createWithIps(123, ['127.0.0.2'])
                ),
                'ip' => '127.0.0.1',
                'expectedHas' => false,
            ],
            'single IP, matching' => [
                'instance' => InstanceFactory::create(
                    DropletDataFactory::createWithIps(123, ['127.0.0.1'])
                ),
                'ip' => '127.0.0.1',
                'expectedHas' => true,
            ],
            'three IPs, third matching' => [
                'instance' => InstanceFactory::create(
                    DropletDataFactory::createWithIps(123, ['127.0.0.1', '127.0.0.2', '127.0.0.3'])
                ),
                'ip' => '127.0.0.3',
                'expectedHas' => true,
            ],
        ];
    }

    /**
     * @dataProvider getLabelDataProvider
     */
    public function testGetLabel(Instance $instance, string $expectedLabel): void
    {
        self::assertSame($expectedLabel, $instance->getLabel());
    }

    /**
     * @return array<mixed>
     */
    public function getLabelDataProvider(): array
    {
        return [
            'no tags' => [
                'instance' => InstanceFactory::create([
                    'id' => 123,
                ]),
                'expectedLabel' => '123 ([no tags])',
            ],
            'single tag' => [
                'instance' => InstanceFactory::create([
                    'id' => 456,
                    'tags' => [
                        'tag1',
                    ],
                ]),
                'expectedLabel' => '456 (tag1)',
            ],
            'multiple tags' => [
                'instance' => InstanceFactory::create([
                    'id' => 789,
                    'tags' => [
                        'tag1',
                        'tag2',
                        'tag3',
                    ],
                ]),
                'expectedLabel' => '789 (tag1, tag2, tag3)',
            ],
        ];
    }

    /**
     * @dataProvider jsonSerializeDataProvider
     *
     * @param array<mixed> $expected
     */
    public function testJsonSerialize(Instance $instance, array $expected): void
    {
        self::assertSame($expected, $instance->jsonSerialize());
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerializeDataProvider(): array
    {
        return [
            'id-only' => [
                'instance' => InstanceFactory::create([
                    'id' => 123,
                    'created_at' => '2020-01-02T01:02:03.000Z'
                ]),
                'expected' => [
                    'id' => 123,
                    'state' => [
                        'ips' => [],
                        'created_at' => '2020-01-02T01:02:03.000Z'
                    ],
                ],
            ],
            'id and IP addresses' => [
                'instance' => InstanceFactory::create(array_merge(
                    DropletDataFactory::createWithIps(
                        456,
                        [
                            '127.0.0.1',
                            '10.0.0.1',
                        ]
                    ),
                    [
                        'created_at' => '2020-01-02T04:05:06.000Z'
                    ]
                )),
                'expected' => [
                    'id' => 456,
                    'state' => [
                        'ips' => [
                            '127.0.0.1',
                            '10.0.0.1',
                        ],
                        'created_at' => '2020-01-02T04:05:06.000Z'
                    ],
                ],
            ],
            'id, no IP addresses, additional custom state' => [
                'instance' => InstanceFactory::create([
                    'id' => 789,
                    'created_at' => '2020-01-02T07:08:09.000Z'
                ])->withAdditionalState([
                    'key1' => 'value1',
                    'key2' => 'value2',
                ]),
                'expected' => [
                    'id' => 789,
                    'state' => [
                        'key1' => 'value1',
                        'key2' => 'value2',
                        'ips' => [],
                        'created_at' => '2020-01-02T07:08:09.000Z'
                    ],
                ],
            ],
            'id, IP addresses, additional custom state' => [
                'instance' => InstanceFactory::create(array_merge(
                    DropletDataFactory::createWithIps(
                        321,
                        [
                            '127.0.0.2',
                            '10.0.0.2',
                        ]
                    ),
                    [
                        'created_at' => '2020-01-02T03:02:01.000Z'
                    ]
                ))->withAdditionalState([
                    'key1' => 'value1',
                    'key2' => 'value2',
                ]),
                'expected' => [
                    'id' => 321,
                    'state' => [
                        'key1' => 'value1',
                        'key2' => 'value2',
                        'ips' => [
                            '127.0.0.2',
                            '10.0.0.2',
                        ],
                        'created_at' => '2020-01-02T03:02:01.000Z'
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider getDropletStatusDataProvider
     */
    public function testGetDropletStatus(Instance $instance, string $expectedDropletStatus): void
    {
        self::assertSame($expectedDropletStatus, $instance->getDropletStatus());
    }

    /**
     * @return array<mixed>
     */
    public function getDropletStatusDataProvider(): array
    {
        return [
            'status: new' => [
                'instance' => InstanceFactory::create([
                    'id' => 123,
                    'status' => Instance::DROPLET_STATUS_NEW,
                ]),
                'expectedDropletStatus' => Instance::DROPLET_STATUS_NEW,
            ],
            'status: active' => [
                'instance' => InstanceFactory::create([
                    'id' => 123,
                    'status' => Instance::DROPLET_STATUS_ACTIVE,
                ]),
                'expectedDropletStatus' => Instance::DROPLET_STATUS_ACTIVE,
            ],
            'status: off' => [
                'instance' => InstanceFactory::create([
                    'id' => 123,
                    'status' => 'off',
                ]),
                'expectedDropletStatus' => 'off',
            ],
            'status: archive' => [
                'instance' => InstanceFactory::create([
                    'id' => 123,
                    'status' => Instance::DROPLET_STATUS_ARCHIVE,
                ]),
                'expectedDropletStatus' => Instance::DROPLET_STATUS_ARCHIVE,
            ],
            'status: unknown' => [
                'instance' => InstanceFactory::create([
                    'id' => 123,
                    'status' => 'foo',
                ]),
                'expectedDropletStatus' => Instance::DROPLET_STATUS_UNKNOWN,
            ],
        ];
    }

    /**
     * @dataProvider getStateDataProvider
     *
     * @param array<mixed> $expected
     */
    public function testGetState(Instance $instance, array $expected): void
    {
        self::assertSame($expected, $instance->getState());
    }

    /**
     * @return array<mixed>
     */
    public function getStateDataProvider(): array
    {
        $now = '2022-04-14T16:40:05.000Z';
        $yesterday = '2022-04-13T16:40:05.000Z';

        return [
            'id and created_at only' => [
                'instance' => InstanceFactory::create([
                    'id' => 123,
                    'created_at' => $now,
                ]),
                'expected' => [
                    'ips' => [],
                    'created_at' => $now,
                ],
            ],
            'id, created_at and ip addresses' => [
                'instance' => InstanceFactory::create(
                    array_merge(
                        DropletDataFactory::createWithIps(123, ['127.0.0.1', '127.0.0.2']),
                        [
                            'created_at' => $yesterday,
                        ]
                    )
                ),
                'expected' => [
                    'ips' => [
                        '127.0.0.1',
                        '127.0.0.2',
                    ],
                    'created_at' => $yesterday,
                ],
            ],
        ];
    }
}
