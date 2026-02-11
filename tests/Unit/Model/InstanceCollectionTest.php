<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Instance;
use App\Model\InstanceCollection;
use DigitalOceanV2\Entity\Droplet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InstanceCollectionTest extends TestCase
{
    #[DataProvider('getNewestDataProvider')]
    public function testGetNewest(InstanceCollection $collection, ?Instance $expectedNewest): void
    {
        self::assertEquals($expectedNewest, $collection->getNewest());
    }

    /**
     * @return array<mixed>
     */
    public static function getNewestDataProvider(): array
    {
        $sortedCollection = new InstanceCollection([
            new Instance(
                new Droplet([
                    'id' => 123,
                    'created_at' => '2021-07-30T16:36:31Z',
                ])
            ),
            new Instance(
                new Droplet([
                    'id' => 465,
                    'created_at' => '2021-07-29T16:36:31Z',
                ])
            ),
            new Instance(
                new Droplet([
                    'id' => 789,
                    'created_at' => '2021-07-28T16:36:31Z',
                ])
            ),
        ]);

        $reverseSortedCollection = new InstanceCollection([
            new Instance(
                new Droplet([
                    'id' => 789,
                    'created_at' => '2021-07-28T16:36:31Z',
                ])
            ),
            new Instance(
                new Droplet([
                    'id' => 465,
                    'created_at' => '2021-07-29T16:36:31Z',
                ])
            ),
            new Instance(
                new Droplet([
                    'id' => 123,
                    'created_at' => '2021-07-30T16:36:31Z',
                ])
            ),
        ]);

        $expectedNewest = new Instance(
            new Droplet([
                'id' => 123,
                'created_at' => '2021-07-30T16:36:31Z',
            ])
        );

        return [
            'empty' => [
                'collection' => new InstanceCollection([]),
                'expectedNewest' => null,
            ],
            'sorted' => [
                'collection' => $sortedCollection,
                'expectedNewest' => $expectedNewest,
            ],
            'reverse sorted' => [
                'collection' => $reverseSortedCollection,
                'expectedNewest' => $expectedNewest,
            ],
        ];
    }

    /**
     * @param array<mixed> $expected
     */
    #[DataProvider('jsonSerializeDataProvider')]
    public function testJsonSerialize(InstanceCollection $collection, array $expected): void
    {
        self::assertSame($expected, $collection->jsonSerialize());
    }

    /**
     * @return array<mixed>
     */
    public static function jsonSerializeDataProvider(): array
    {
        return [
            'empty' => [
                'collection' => new InstanceCollection([]),
                'expected' => [],
            ],
            'single, id-only' => [
                'collection' => new InstanceCollection([
                    new Instance(
                        new Droplet([
                            'id' => 123,
                            'created_at' => '2020-01-02T01:02:03.000Z',
                        ])
                    ),
                ]),
                'expected' => [
                    [
                        'id' => 123,
                        'state' => [
                            'ips' => [],
                            'created_at' => '2020-01-02T01:02:03.000Z',
                        ],
                    ],
                ],
            ],
            'multiple' => [
                'collection' => new InstanceCollection([
                    new Instance(
                        new Droplet([
                            'id' => 465,
                            'created_at' => '2020-01-02T04:05:06.000Z',
                        ])
                    ),
                    new Instance(
                        new Droplet([
                            'id' => 789,
                            'created_at' => '2020-01-02T07:08:09.000Z',
                            'networks' => (object) [
                                'v4' => (object) [
                                    (object) [
                                        'ip_address' => '127.0.0.1',
                                        'type' => 'public',
                                    ],
                                    (object) [
                                        'ip_address' => '10.0.0.1',
                                        'type' => 'public',
                                    ],
                                ],
                            ],
                        ])
                    ),
                    new Instance(
                        new Droplet([
                            'id' => 321,
                            'created_at' => '2020-01-02T03:02:01.000Z',
                            'networks' => (object) [
                                'v4' => (object) [
                                    (object) [
                                        'ip_address' => '127.0.0.2',
                                        'type' => 'public',
                                    ],
                                    (object) [
                                        'ip_address' => '10.0.0.2',
                                        'type' => 'public',
                                    ],
                                ],
                            ],
                        ])
                    ),
                ]),
                'expected' => [
                    [
                        'id' => 465,
                        'state' => [
                            'ips' => [],
                            'created_at' => '2020-01-02T04:05:06.000Z',
                        ],
                    ],
                    [
                        'id' => 789,
                        'state' => [
                            'ips' => [
                                '127.0.0.1',
                                '10.0.0.1',
                            ],
                            'created_at' => '2020-01-02T07:08:09.000Z',
                        ],
                    ],
                    [
                        'id' => 321,
                        'state' => [
                            'ips' => [
                                '127.0.0.2',
                                '10.0.0.2',
                            ],
                            'created_at' => '2020-01-02T03:02:01.000Z',
                        ],
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('findByIpDataProvider')]
    public function testFindByIp(InstanceCollection $collection, string $ip, ?Instance $expected): void
    {
        self::assertSame($expected, $collection->findByIP($ip));
    }

    /**
     * @return array<mixed>
     */
    public static function findByIpDataProvider(): array
    {
        $ip = '127.0.0.1';

        $instanceWithMatchingIPWithSingleIP = new Instance(
            new Droplet([
                'id' => 123,
                'networks' => (object) [
                    'v4' => (object) [
                        (object) [
                            'ip_address' => '127.0.0.1',
                            'type' => 'public',
                        ],
                    ],
                ],
            ])
        );

        $instanceWithMatchingIPWithMultipleIPs = new Instance(
            new Droplet([
                'id' => 123,
                'networks' => (object) [
                    'v4' => (object) [
                        (object) [
                            'ip_address' => '127.0.0.3',
                            'type' => 'public',
                        ],
                        (object) [
                            'ip_address' => '127.0.0.2',
                            'type' => 'public',
                        ],
                        (object) [
                            'ip_address' => '127.0.0.1',
                            'type' => 'public',
                        ],
                    ],
                ],
            ])
        );

        return [
            'empty' => [
                'collection' => new InstanceCollection(),
                'ip' => $ip,
                'expected' => null,
            ],
            'single instance with no IPs' => [
                'collection' => new InstanceCollection([
                    new Instance(new Droplet(['id' => 123])),
                ]),
                'ip' => $ip,
                'expected' => null,
            ],
            'multiple instance with no IPs' => [
                'collection' => new InstanceCollection([
                    new Instance(new Droplet(['id' => 123])),
                    new Instance(new Droplet(['id' => 456])),
                    new Instance(new Droplet(['id' => 789])),
                ]),
                'ip' => $ip,
                'expected' => null,
            ],
            'single instance with non-matching IP' => [
                'collection' => new InstanceCollection([
                    new Instance(
                        new Droplet([
                            'id' => 123,
                            'networks' => (object) [
                                'v4' => (object) [
                                    (object) [
                                        'ip_address' => '127.0.0.2',
                                        'type' => 'public',
                                    ],
                                ],
                            ],
                        ])
                    ),
                ]),
                'ip' => $ip,
                'expected' => null,
            ],
            'multiple instance with non-matching IPs' => [
                'collection' => new InstanceCollection([
                    new Instance(
                        new Droplet([
                            'id' => 123,
                            'networks' => (object) [
                                'v4' => (object) [
                                    (object) [
                                        'ip_address' => '127.0.0.2',
                                        'type' => 'public',
                                    ],
                                ],
                            ],
                        ])
                    ),
                    new Instance(
                        new Droplet([
                            'id' => 456,
                            'networks' => (object) [
                                'v4' => (object) [
                                    (object) [
                                        'ip_address' => '127.0.0.3',
                                        'type' => 'public',
                                    ],
                                ],
                            ],
                        ])
                    ),
                    new Instance(
                        new Droplet([
                            'id' => 789,
                            'networks' => (object) [
                                'v4' => (object) [
                                    (object) [
                                        'ip_address' => '127.0.0.4',
                                        'type' => 'public',
                                    ],
                                ],
                            ],
                        ])
                    ),
                ]),
                'ip' => $ip,
                'expected' => null,
            ],
            'single instance with single matching IP' => [
                'collection' => new InstanceCollection([
                    $instanceWithMatchingIPWithSingleIP,
                ]),
                'ip' => $ip,
                'expected' => $instanceWithMatchingIPWithSingleIP,
            ],
            'single instance with three IPs, third matching IP' => [
                'collection' => new InstanceCollection([
                    $instanceWithMatchingIPWithMultipleIPs,
                ]),
                'ip' => $ip,
                'expected' => $instanceWithMatchingIPWithMultipleIPs,
            ],
            'multiple instances with single instance having matching IP' => [
                'collection' => new InstanceCollection([
                    new Instance(
                        new Droplet([
                            'id' => 123,
                            'networks' => (object) [
                                'v4' => (object) [
                                    (object) [
                                        'ip_address' => '127.0.0.2',
                                        'type' => 'public',
                                    ],
                                ],
                            ],
                        ])
                    ),
                    $instanceWithMatchingIPWithSingleIP,
                    new Instance(
                        new Droplet([
                            'id' => 456,
                            'networks' => (object) [
                                'v4' => (object) [
                                    (object) [
                                        'ip_address' => '127.0.0.3',
                                        'type' => 'public',
                                    ],
                                ],
                            ],
                        ])
                    ),
                ]),
                'ip' => $ip,
                'expected' => $instanceWithMatchingIPWithSingleIP,
            ],
        ];
    }
}
