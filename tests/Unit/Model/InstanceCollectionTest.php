<?php

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
        $expectedNewest = $sortedCollection->getFirst();

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
     * @dataProvider filterDataProvider
     */
    public function testFilter(
        InstanceCollection $collection,
        Filter $filter,
        InstanceCollection $expectedCollection
    ): void {
        self::assertEquals(
            $expectedCollection,
            $collection->filter($filter)
        );
    }

    /**
     * @return array<mixed>
     */
    public function filterDataProvider(): array
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
                    ])
                ]),
                'expected' => [
                    [
                        'id' => 123,
                        'state' => [
                            'ips' => [],
                        ],
                    ],
                ],
            ],
            'multiple' => [
                'collection' => new InstanceCollection([
                    InstanceFactory::create([
                        'id' => 465,
                    ]),
                    InstanceFactory::create(DropletDataFactory::createWithIps(
                        789,
                        [
                            '127.0.0.1',
                            '10.0.0.1',
                        ],
                    )),
                    InstanceFactory::create(DropletDataFactory::createWithIps(
                        321,
                        [
                            '127.0.0.2',
                            '10.0.0.2',
                        ],
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
                        ],
                    ],
                    [
                        'id' => 789,
                        'state' => [
                            'ips' => [
                                '127.0.0.1',
                                '10.0.0.1',
                            ],
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
                        ],
                    ],
                ],
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
