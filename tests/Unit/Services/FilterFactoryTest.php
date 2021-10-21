<?php

namespace App\Tests\Unit\Services;

use App\Model\Filter;
use App\Model\FilterInterface;
use App\Services\FilterFactory;
use PHPUnit\Framework\TestCase;

class FilterFactoryTest extends TestCase
{
    /**
     * @dataProvider createFiltersFromStringDataProvider
     *
     * @param FilterInterface::MATCH_TYPE_* $matchType
     * @param Filter[]                      $expected
     */
    public function testCreateFromString(string $filter, string $matchType, array $expected): void
    {
        $filterStringParser = new FilterFactory();

        self::assertEquals($expected, $filterStringParser->createFromString($filter, $matchType));
    }

    /**
     * @return array<mixed>
     */
    public function createFiltersFromStringDataProvider(): array
    {
        return [
            'empty' => [
                'filter' => '',
                'matchType' => FilterInterface::MATCH_TYPE_POSITIVE,
                'expected' => [],
            ],
            'non-json' => [
                'filter' => 'content',
                'matchType' => FilterInterface::MATCH_TYPE_POSITIVE,
                'expected' => [],
            ],
            'single invalid filter, field is not a string' => [
                'filter' => json_encode([
                    [
                        0 => 12,
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_POSITIVE,
                'expected' => [],
            ],
            'single invalid filter, value is not scalar' => [
                'filter' => json_encode([
                    [
                        'message-queue-size' => [''],
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_POSITIVE,
                'expected' => [],
            ],
            'single valid filter, boolean value, positive match' => [
                'filter' => json_encode([
                    [
                        'is-active' => true,
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_POSITIVE,
                'expected' => [
                    new Filter('is-active', true, FilterInterface::MATCH_TYPE_POSITIVE),
                ],
            ],
            'single valid filter, boolean value, negative match' => [
                'filter' => json_encode([
                    [
                        'is-active' => true,
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_NEGATIVE,
                'expected' => [
                    new Filter('is-active', true, FilterInterface::MATCH_TYPE_NEGATIVE),
                ],
            ],
            'single valid filter, float value, positive match' => [
                'filter' => json_encode([
                    [
                        'radius' => M_PI,
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_POSITIVE,
                'expected' => [
                    new Filter('radius', M_PI, FilterInterface::MATCH_TYPE_POSITIVE),
                ],
            ],
            'single valid filter, float value, negative match' => [
                'filter' => json_encode([
                    [
                        'radius' => M_PI,
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_NEGATIVE,
                'expected' => [
                    new Filter('radius', M_PI, FilterInterface::MATCH_TYPE_NEGATIVE),
                ],
            ],
            'single valid filter, integer value, positive match' => [
                'filter' => json_encode([
                    [
                        'message-queue-size' => 12,
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_POSITIVE,
                'expected' => [
                    new Filter('message-queue-size', 12, FilterInterface::MATCH_TYPE_POSITIVE),
                ],
            ],
            'single valid filter, integer value, negative match' => [
                'filter' => json_encode([
                    [
                        'message-queue-size' => 12,
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_NEGATIVE,
                'expected' => [
                    new Filter('message-queue-size', 12, FilterInterface::MATCH_TYPE_NEGATIVE),
                ],
            ],
            'single valid filter, string value, positive match' => [
                'filter' => json_encode([
                    [
                        'status' => 'delayed',
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_POSITIVE,
                'expected' => [
                    new Filter('status', 'delayed', FilterInterface::MATCH_TYPE_POSITIVE),
                ],
            ],
            'single valid filter, string value, negative match' => [
                'filter' => json_encode([
                    [
                        'status' => 'delayed',
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_NEGATIVE,
                'expected' => [
                    new Filter('status', 'delayed', FilterInterface::MATCH_TYPE_NEGATIVE),
                ],
            ],
            'multiple valid filters, positive match' => [
                'filter' => json_encode([
                    [
                        'is-sleeping' => false,
                    ],
                    [
                        'diameter' => M_2_PI,
                    ],
                    [
                        'count' => 7,
                    ],
                    [
                        'message' => 'Hello',
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_POSITIVE,
                'expected' => [
                    new Filter('is-sleeping', false, FilterInterface::MATCH_TYPE_POSITIVE),
                    new Filter('diameter', M_2_PI, FilterInterface::MATCH_TYPE_POSITIVE),
                    new Filter('count', 7, FilterInterface::MATCH_TYPE_POSITIVE),
                    new Filter('message', 'Hello', FilterInterface::MATCH_TYPE_POSITIVE),
                ],
            ],
            'multiple valid filters, negative match' => [
                'filter' => json_encode([
                    [
                        'is-sleeping' => false,
                    ],
                    [
                        'diameter' => M_2_PI,
                    ],
                    [
                        'count' => 7,
                    ],
                    [
                        'message' => 'Hello',
                    ],
                ]),
                'matchType' => FilterInterface::MATCH_TYPE_NEGATIVE,
                'expected' => [
                    new Filter('is-sleeping', false, FilterInterface::MATCH_TYPE_NEGATIVE),
                    new Filter('diameter', M_2_PI, FilterInterface::MATCH_TYPE_NEGATIVE),
                    new Filter('count', 7, FilterInterface::MATCH_TYPE_NEGATIVE),
                    new Filter('message', 'Hello', FilterInterface::MATCH_TYPE_NEGATIVE),
                ],
            ],
        ];
    }
}
