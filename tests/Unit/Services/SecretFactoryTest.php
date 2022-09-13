<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Model\Secret;
use App\Services\SecretFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class SecretFactoryTest extends TestCase
{
    private SecretFactory $secretFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretFactory = new SecretFactory();
    }

    /**
     * @dataProvider createFromJsonForKeysMatchingPrefixDataProvider
     *
     * @param string[]                $prefixes
     * @param Collection<int, Secret> $expected
     */
    public function testCreateFromJsonForKeysMatchingPrefix(
        array $prefixes,
        string $secretsJson,
        Collection $expected
    ): void {
        self::assertEquals(
            $expected,
            $this->secretFactory->createFromJsonForKeysMatchingPrefix($prefixes, $secretsJson)
        );
    }

    /**
     * @return array<mixed>
     */
    public function createFromJsonForKeysMatchingPrefixDataProvider(): array
    {
        return [
            'no prefixes, empty json' => [
                'prefixes' => [],
                'secretsJson' => '',
                'expected' => new ArrayCollection([]),
            ],
            'no prefixes, non-empty json' => [
                'prefixes' => [],
                'secretsJson' => '{"foo_key_1":"foo 1 value", "foo_key_2":"foo 2 value"}',
                'expected' => new ArrayCollection([]),
            ],
            'no matching prefixes' => [
                'prefixes' => ['non-matching-1', 'non-matching-2'],
                'secretsJson' => '{"foo_key_1":"foo 1 value", "foo_key_2":"foo 2 value"}',
                'expected' => new ArrayCollection([]),
            ],
            'single prefix matches all' => [
                'prefixes' => ['foo_'],
                'secretsJson' => '{"foo_key_1":"foo 1 value", "foo_key_2":"foo 2 value"}',
                'expected' => new ArrayCollection([
                    new Secret('foo_key_1', 'foo 1 value'),
                    new Secret('foo_key_2', 'foo 2 value'),
                ]),
            ],
            'single prefix matches foo subset' => [
                'prefixes' => ['foo_'],
                'secretsJson' => '{"foo_key_1":"foo 1 value", "foo_key_2":"foo 2 value", "bar_key_1":"bar 1 value"}',
                'expected' => new ArrayCollection([
                    new Secret('foo_key_1', 'foo 1 value'),
                    new Secret('foo_key_2', 'foo 2 value'),
                ]),
            ],
            'single prefix matches bar subset' => [
                'prefixes' => ['bar_'],
                'secretsJson' => '{"foo_key_1":"foo 1 value", "foo_key_2":"foo 2 value", "bar_key_1":"bar 1 value"}',
                'expected' => new ArrayCollection([
                    2 => new Secret('bar_key_1', 'bar 1 value'),
                ]),
            ],
            'multiple prefixes matches all' => [
                'prefixes' => ['foo_', 'bar_'],
                'secretsJson' => '{"foo_key_1":"foo 1 value", "foo_key_2":"foo 2 value", "bar_key_1":"bar 1 value"}',
                'expected' => new ArrayCollection([
                    new Secret('foo_key_1', 'foo 1 value'),
                    new Secret('foo_key_2', 'foo 2 value'),
                    new Secret('bar_key_1', 'bar 1 value'),
                ]),
            ],
            'multiple prefixes matches subset' => [
                'prefixes' => ['foo_', 'bar_'],
                'secretsJson' => '{"foo_key":"foo value", "bar_key":"bar value", "foobar_key":"foobar value"}',
                'expected' => new ArrayCollection([
                    new Secret('foo_key', 'foo value'),
                    new Secret('bar_key', 'bar value'),
                ]),
            ],
        ];
    }
}
