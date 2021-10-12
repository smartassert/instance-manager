<?php

namespace App\Tests\Unit\Services;

use App\Model\Filter;
use App\Model\FilterInterface;
use App\Services\FilterFactory;
use PHPUnit\Framework\TestCase;

class FilterFactoryTest extends TestCase
{
    /**
     * @dataProvider createPositiveFiltersFromStringDataProvider
     *
     * @param Filter[] $expected
     */
    public function testCreatePositiveFiltersFromString(string $filter, array $expected): void
    {
        $filterStringParser = new FilterFactory();

        self::assertEquals($expected, $filterStringParser->createPositiveFiltersFromString($filter));
    }

    /**
     * @return array<mixed>
     */
    public function createPositiveFiltersFromStringDataProvider(): array
    {
        return $this->createFiltersFromStringDataProvider(FilterInterface::MATCH_TYPE_POSITIVE);
    }

    /**
     * @dataProvider createNegativeFiltersFromStringDataProvider
     *
     * @param Filter[] $expected
     */
    public function testCreateNegativeFiltersFromString(string $filter, array $expected): void
    {
        $filterStringParser = new FilterFactory();

        self::assertEquals($expected, $filterStringParser->createNegativeFiltersFromString($filter));
    }

    /**
     * @return array<mixed>
     */
    public function createNegativeFiltersFromStringDataProvider(): array
    {
        return $this->createFiltersFromStringDataProvider(FilterInterface::MATCH_TYPE_NEGATIVE);
    }

    /**
     * @param FilterInterface::MATCH_TYPE_* $matchType
     *
     * @return array<mixed>
     */
    private function createFiltersFromStringDataProvider(string $matchType): array
    {
        return [
            'empty' => [
                'filter' => '',
                'expected' => [],
            ],
            'non-json' => [
                'filter' => 'content',
                'expected' => [],
            ],
            'single invalid filter, field is not a string' => [
                'filter' => json_encode([
                    [
                        0 => 12,
                    ],
                ]),
                'expected' => [],
            ],
            'single invalid filter, value is not scalar' => [
                'filter' => json_encode([
                    [
                        'message-queue-size' => [''],
                    ],
                ]),
                'expected' => [],
            ],
            'single valid filter, boolean value' => [
                'filter' => json_encode([
                    [
                        'is-active' => true,
                    ],
                ]),
                'expected' => [
                    new Filter('is-active', true, $matchType),
                ],
            ],
            'single valid filter, float value' => [
                'filter' => json_encode([
                    [
                        'radius' => M_PI,
                    ],
                ]),
                'expected' => [
                    new Filter('radius', M_PI, $matchType),
                ],
            ],
            'single valid filter, integer value' => [
                'filter' => json_encode([
                    [
                        'message-queue-size' => 12,
                    ],
                ]),
                'expected' => [
                    new Filter('message-queue-size', 12, $matchType),
                ],
            ],
            'single valid filter, string value' => [
                'filter' => json_encode([
                    [
                        'status' => 'delayed',
                    ],
                ]),
                'expected' => [
                    new Filter('status', 'delayed', $matchType),
                ],
            ],
            'multiple valid filters' => [
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
                'expected' => [
                    new Filter('is-sleeping', false, $matchType),
                    new Filter('diameter', M_2_PI, $matchType),
                    new Filter('count', 7, $matchType),
                    new Filter('message', 'Hello', $matchType),
                ],
            ],
        ];
    }
}
