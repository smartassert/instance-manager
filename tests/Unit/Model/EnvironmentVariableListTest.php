<?php

namespace App\Tests\Unit\Model;

use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableList;
use PHPUnit\Framework\TestCase;
use webignition\ObjectReflector\ObjectReflector;

class EnvironmentVariableListTest extends TestCase
{
    /**
     * @dataProvider getItemsDataProvider
     *
     * @param EnvironmentVariable[] $expectedItems
     */
    public function testGetItems(EnvironmentVariableList $list, array $expectedItems): void
    {
        $items = ObjectReflector::getProperty($list, 'environmentVariables');

        self::assertEqualsCanonicalizing($expectedItems, $items);
    }

    /**
     * @return array<mixed>
     */
    public function getItemsDataProvider(): array
    {
        return [
            'empty' => [
                'list' => new EnvironmentVariableList([]),
                'expectedItems' => [],
            ],
            'invalid items' => [
                'list' => new EnvironmentVariableList([
                    '',
                    'value',
                    '=value',
                ]),
                'expectedItems' => [],
            ],
            'valid items' => [
                'list' => new EnvironmentVariableList([
                    'key3=value3',
                    'key1=value1',
                    'key2=value2',
                ]),
                'expectedItems' => [
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                    new EnvironmentVariable('key3', 'value3'),
                ],
            ],
            'valid and invalid items' => [
                'list' => new EnvironmentVariableList([
                    '',
                    '=value',
                    'key3=value3',
                    'value',
                    'key1=value1',
                    'key2=value2',
                ]),
                'expectedItems' => [
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                    new EnvironmentVariable('key3', 'value3'),
                ],
            ],
        ];
    }

    public function testIterator(): void
    {
        $list = new EnvironmentVariableList([
            'key3=value3',
            'key1=value1',
            'key2=value2',
        ]);

        $expectedItems = [
            new EnvironmentVariable('key3', 'value3'),
            new EnvironmentVariable('key1', 'value1'),
            new EnvironmentVariable('key2', 'value2'),
        ];

        foreach ($list as $key => $item) {
            self:self::assertEquals($expectedItems[$key], $item);
        }
    }
}
