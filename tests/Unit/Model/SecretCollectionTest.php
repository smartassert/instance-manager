<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Secret;
use App\Model\SecretCollection;
use PHPUnit\Framework\TestCase;

class SecretCollectionTest extends TestCase
{
    /**
     * @dataProvider filterByKeyPrefixesDataProvider
     *
     * @param string[] $prefixes
     */
    public function testFilterByKeyPrefixes(
        SecretCollection $collection,
        array $prefixes,
        SecretCollection $expected
    ): void {
        self::assertEquals($expected, $collection->filterByKeyPrefixes($prefixes));
    }

    /**
     * @return array<mixed>
     */
    public function filterByKeyPrefixesDataProvider(): array
    {
        return [
            'empty collection, no prefixes' => [
                'collection' => new SecretCollection(),
                'prefixes' => [],
                'expected' => new SecretCollection(),
            ],
            'non-empty collection, no prefixes' => [
                'collection' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                ]),
                'prefixes' => [],
                'expected' => new SecretCollection(),
            ],
            'no matching prefixes' => [
                'collection' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                ]),
                'prefixes' => [
                    'bar_',
                ],
                'expected' => new SecretCollection(),
            ],
            'single prefix matches all in single-item collection' => [
                'collection' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                ]),
                'prefixes' => ['foo_'],
                'expected' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                ]),
            ],
            'single prefix matches all in multiple-item collection' => [
                'collection' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                    new Secret('foo_2', 'foo_2 value'),
                    new Secret('bar_1', 'bar_1 value'),
                ]),
                'prefixes' => ['foo_'],
                'expected' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                    new Secret('foo_2', 'foo_2 value'),
                ]),
            ],
            'single prefix matches foo subset' => [
                'collection' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                    new Secret('foo_2', 'foo_2 value'),
                    new Secret('bar_1', 'bar_1 value'),
                ]),
                'prefixes' => ['foo_'],
                'expected' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                    new Secret('foo_2', 'foo_2 value'),
                ]),
            ],
            'single prefix matches bar subset' => [
                'collection' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                    new Secret('foo_2', 'foo_2 value'),
                    new Secret('bar_1', 'bar_1 value'),
                ]),
                'prefixes' => ['bar_'],
                'expected' => new SecretCollection([
                    new Secret('bar_1', 'bar_1 value'),
                ]),
            ],
            'multiple prefixes matches all' => [
                'collection' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                    new Secret('foo_2', 'foo_2 value'),
                    new Secret('bar_1', 'bar_1 value'),
                ]),
                'prefixes' => ['foo_', 'bar_'],
                'expected' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                    new Secret('foo_2', 'foo_2 value'),
                    new Secret('bar_1', 'bar_1 value'),
                ]),
            ],
            'multiple prefixes matches subset' => [
                'collection' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                    new Secret('foo_2', 'foo_2 value'),
                    new Secret('bar_1', 'bar_1 value'),
                    new Secret('foobar_1', 'foobar_1 value'),
                ]),
                'prefixes' => ['foo_', 'bar_'],
                'expected' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                    new Secret('foo_2', 'foo_2 value'),
                    new Secret('bar_1', 'bar_1 value'),
                ]),
            ],
        ];
    }
}
