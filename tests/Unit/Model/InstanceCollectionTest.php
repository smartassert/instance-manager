<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Filter;
use App\Model\FilterInterface;
use App\Model\Instance;
use App\Model\InstanceCollection;
use App\Tests\Services\DropletDataFactory;
use App\Tests\Services\InstanceFactory;
use PHPUnit\Framework\TestCase;

class InstanceCollectionTest extends TestCase
{
    /**
     * @dataProvider getNewestDataProvider
     */
    public function testGetNewest(InstanceCollection $collection, ?Instance $expectedNewest): void
    {
        self::assertEquals($expectedNewest, $collection->getNewest());
    }

    /**
     * @return array<mixed>
     */
    public function getNewestDataProvider(): array
    {
        $sortedCollection = $this->createSortedCollection();
        $reverseSortedCollection = $this->createReverseSortedCollection();
        $expectedNewest = $sortedCollection->first();

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
     * @dataProvider filterByFilterDataProvider
     */
    public function testFilterByFilter(
        InstanceCollection $collection,
        Filter $filter,
        InstanceCollection $expectedCollection
    ): void {
        self::assertEquals(
            $expectedCollection,
            $collection->filterByFilter($filter)
        );
    }

    /**
     * @return array<mixed>
     */
    public function filterByFilterDataProvider(): array
    {
        $ip = '127.0.0.1';
        $instanceWithIp = InstanceFactory::create(DropletDataFactory::createWithIps(123, [$ip]));
        $instanceWithoutIp1 = InstanceFactory::create(DropletDataFactory::createWithIps(456, ['127.0.0.2']));
        $instanceWithoutIp2 = InstanceFactory::create(DropletDataFactory::createWithIps(789, ['127.0.0.3']));

        $instanceWithNonEmptyMessageQueue = InstanceFactory::create([
            'id' => 123,
        ])
            ->withAdditionalState([
                'message-queue-size' => 1,
            ])
        ;

        $instanceWithEmptyMessageQueue1 = InstanceFactory::create([
            'id' => 456,
        ])
            ->withAdditionalState([
                'message-queue-size' => 0,
            ])
        ;

        $instanceWithEmptyMessageQueue2 = InstanceFactory::create([
            'id' => 789,
        ])
            ->withAdditionalState([
                'message-queue-size' => 0,
            ])
        ;

        $notHasIpFilter = new Filter('ips', $ip, FilterInterface::MATCH_TYPE_NEGATIVE);
        $hasEmptyMessageQueueFilter = new Filter('message-queue-size', 0, FilterInterface::MATCH_TYPE_POSITIVE);

        return [
            'empty, not has IP filter' => [
                'collection' => new InstanceCollection([]),
                'filter' => $notHasIpFilter,
                'expectedCollection' => new InstanceCollection([]),
            ],
            'single, not has IP filter, has IP' => [
                'collection' => new InstanceCollection([
                    $instanceWithIp,
                ]),
                'filter' => $notHasIpFilter,
                'expectedCollection' => new InstanceCollection([]),
            ],
            'single, not has IP filter, does not have IP' => [
                'collection' => new InstanceCollection([
                    $instanceWithoutIp1,
                ]),
                'filter' => $notHasIpFilter,
                'expectedCollection' => new InstanceCollection([
                    $instanceWithoutIp1,
                ]),
            ],
            'multiple, not has IP filter, one has IP' => [
                'collection' => new InstanceCollection([
                    $instanceWithoutIp1,
                    $instanceWithIp,
                    $instanceWithoutIp2,
                ]),
                'filter' => $notHasIpFilter,
                'expectedCollection' => new InstanceCollection([
                    $instanceWithoutIp1,
                    $instanceWithoutIp2,
                ]),
            ],
            'empty, has empty message queue filter' => [
                'collection' => new InstanceCollection([]),
                'filter' => $hasEmptyMessageQueueFilter,
                'expectedCollection' => new InstanceCollection([]),
            ],
            'single, has empty message queue filter, non-empty message queue' => [
                'collection' => new InstanceCollection([
                    $instanceWithNonEmptyMessageQueue,
                ]),
                'filter' => $hasEmptyMessageQueueFilter,
                'expectedCollection' => new InstanceCollection([]),
            ],
            'single, has empty message queue filter, empty message queue' => [
                'collection' => new InstanceCollection([
                    $instanceWithEmptyMessageQueue1,
                ]),
                'filter' => $hasEmptyMessageQueueFilter,
                'expectedCollection' => new InstanceCollection([
                    $instanceWithEmptyMessageQueue1
                ]),
            ],
            'multiple, has empty message queue filter, one has non-empty message queue' => [
                'collection' => new InstanceCollection([
                    $instanceWithEmptyMessageQueue1,
                    $instanceWithNonEmptyMessageQueue,
                    $instanceWithEmptyMessageQueue2,
                ]),
                'filter' => $hasEmptyMessageQueueFilter,
                'expectedCollection' => new InstanceCollection([
                    $instanceWithEmptyMessageQueue1,
                    $instanceWithEmptyMessageQueue2,
                ]),
            ],
        ];
    }

    /**
     * @dataProvider jsonSerializeDataProvider
     *
     * @param array<mixed> $expected
     */
    public function testJsonSerialize(InstanceCollection $collection, array $expected): void
    {
        self::assertSame($expected, $collection->jsonSerialize());
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerializeDataProvider(): array
    {
        return [
            'empty' => [
                'collection' => new InstanceCollection([]),
                'expected' => [],
            ],
            'single, id-only' => [
                'collection' => new InstanceCollection([
                    InstanceFactory::create([
                        'id' => 123,
                        'created_at' => '2020-01-02T01:02:03.000Z',
                    ])
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
                    InstanceFactory::create([
                        'id' => 465,
                        'created_at' => '2020-01-02T04:05:06.000Z'
                    ]),
                    InstanceFactory::create(array_merge(
                        DropletDataFactory::createWithIps(
                            789,
                            [
                                '127.0.0.1',
                                '10.0.0.1',
                            ],
                        ),
                        [
                            'created_at' => '2020-01-02T07:08:09.000Z',
                        ]
                    )),
                    InstanceFactory::create(array_merge(
                        DropletDataFactory::createWithIps(
                            321,
                            [
                                '127.0.0.2',
                                '10.0.0.2',
                            ],
                        ),
                        [
                            'created_at' => '2020-01-02T03:02:01.000Z',
                        ]
                    ))->withAdditionalState([
                        'key1' => 'value1',
                        'key2' => 'value2',
                    ]),
                ]),
                'expected' => [
                    [
                        'id' => 465,
                        'state' => [
                            'ips' => [],
                            'created_at' => '2020-01-02T04:05:06.000Z'
                        ],
                    ],
                    [
                        'id' => 789,
                        'state' => [
                            'ips' => [
                                '127.0.0.1',
                                '10.0.0.1',
                            ],
                            'created_at' => '2020-01-02T07:08:09.000Z'
                        ],
                    ],
                    [
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
            ],
        ];
    }

    /**
     * @dataProvider findByIpDataProvider
     */
    public function testFindByIp(InstanceCollection $collection, string $ip, ?Instance $expected): void
    {
        self::assertSame($expected, $collection->findByIP($ip));
    }

    /**
     * @return array<mixed>
     */
    public function findByIpDataProvider(): array
    {
        $ip = '127.0.0.1';
        $instanceWithMatchingIPWithSingleIP = InstanceFactory::create(
            DropletDataFactory::createWithIps(123, [$ip])
        );
        $instanceWithMatchingIPWithMultipleIPs = InstanceFactory::create(
            DropletDataFactory::createWithIps(123, ['127.0.0.3', '127.0.0.2', $ip])
        );

        return [
            'empty' => [
                'collection' => new InstanceCollection(),
                'ip' => $ip,
                'expected' => null,
            ],
            'single instance with no IPs' => [
                'collection' => new InstanceCollection([
                    InstanceFactory::create([
                        'id' => 123,
                    ]),
                ]),
                'ip' => $ip,
                'expected' => null,
            ],
            'multiple instance with no IPs' => [
                'collection' => new InstanceCollection([
                    InstanceFactory::create([
                        'id' => 123,
                    ]),
                    InstanceFactory::create([
                        'id' => 456,
                    ]),
                    InstanceFactory::create([
                        'id' => 789,
                    ]),
                ]),
                'ip' => $ip,
                'expected' => null,
            ],
            'single instance with non-matching IP' => [
                'collection' => new InstanceCollection([
                    InstanceFactory::create(
                        DropletDataFactory::createWithIps(123, ['127.0.0.2'])
                    ),
                ]),
                'ip' => $ip,
                'expected' => null,
            ],
            'multiple instance with non-matching IPs' => [
                'collection' => new InstanceCollection([
                    InstanceFactory::create(
                        DropletDataFactory::createWithIps(123, ['127.0.0.2'])
                    ),
                    InstanceFactory::create(
                        DropletDataFactory::createWithIps(456, ['127.0.0.3'])
                    ),
                    InstanceFactory::create(
                        DropletDataFactory::createWithIps(789, ['127.0.0.4'])
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
                    InstanceFactory::create(
                        DropletDataFactory::createWithIps(123, ['127.0.0.2'])
                    ),
                    $instanceWithMatchingIPWithSingleIP,
                    InstanceFactory::create(
                        DropletDataFactory::createWithIps(456, ['127.0.0.3'])
                    ),
                ]),
                'ip' => $ip,
                'expected' => $instanceWithMatchingIPWithSingleIP,
            ],
        ];
    }

    private function createSortedCollection(): InstanceCollection
    {
        return new InstanceCollection([
            InstanceFactory::create([
                'id' => 123,
                'created_at' => '2021-07-30T16:36:31Z'
            ]),
            InstanceFactory::create([
                'id' => 465,
                'created_at' => '2021-07-29T16:36:31Z'
            ]),
            InstanceFactory::create([
                'id' => 789,
                'created_at' => '2021-07-28T16:36:31Z'
            ]),
        ]);
    }

    private function createReverseSortedCollection(): InstanceCollection
    {
        return new InstanceCollection([
            InstanceFactory::create([
                'id' => 789,
                'created_at' => '2021-07-28T16:36:31Z'
            ]),
            InstanceFactory::create([
                'id' => 465,
                'created_at' => '2021-07-29T16:36:31Z'
            ]),
            InstanceFactory::create([
                'id' => 123,
                'created_at' => '2021-07-30T16:36:31Z'
            ]),
        ]);
    }
}
