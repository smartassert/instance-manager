<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Model\Secret;
use App\Model\SecretCollection;
use App\Services\SecretFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecretFactoryTest extends TestCase
{
    private SecretFactory $secretFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretFactory = new SecretFactory();
    }

    #[DataProvider('createFromJsonForKeysMatchingPrefixDataProvider')]
    public function testCreate(string $secretsJson, SecretCollection $expected): void
    {
        self::assertEquals($expected, $this->secretFactory->create($secretsJson));
    }

    /**
     * @return array<mixed>
     */
    public static function createFromJsonForKeysMatchingPrefixDataProvider(): array
    {
        return [
            'empty json' => [
                'secretsJson' => '',
                'expected' => new SecretCollection(),
            ],
            'keys and values are not strings' => [
                'secretsJson' => '{0:true, 1:false}',
                'expected' => new SecretCollection(),
            ],
            'keys are strings and values are not strings' => [
                'secretsJson' => '{"foo_1":100, "foo_2":true}',
                'expected' => new SecretCollection(),
            ],
            'keys and values are all strings' => [
                'secretsJson' => '{"foo_1":"foo_1 value", "foo_2":"foo_2 value"}',
                'expected' => new SecretCollection([
                    new Secret('foo_1', 'foo_1 value'),
                    new Secret('foo_2', 'foo_2 value'),
                ]),
            ],
        ];
    }
}
