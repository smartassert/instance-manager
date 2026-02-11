<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\OutputFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OutputFactoryTest extends TestCase
{
    private OutputFactory $outputFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputFactory = new OutputFactory();
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('createSuccessOutputDataProvider')]
    public function testCreateSuccessOutput(array $data, string $expected): void
    {
        self::assertJsonStringEqualsJsonString(
            $expected,
            $this->outputFactory->createSuccessOutput($data)
        );
    }

    /**
     * @return array<mixed>
     */
    public static function createSuccessOutputDataProvider(): array
    {
        return [
            'empty' => [
                'data' => [],
                'expected' => json_encode([
                    'status' => 'success',
                ]),
            ],
            'non-empty' => [
                'data' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
                'expected' => json_encode([
                    'status' => 'success',
                    'key1' => 'value1',
                    'key2' => 'value2',
                ]),
            ],
        ];
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('createErrorOutputDataProvider')]
    public function testCreateErrorOutput(string $errorCode, array $data, string $expected): void
    {
        self::assertJsonStringEqualsJsonString(
            $expected,
            $this->outputFactory->createErrorOutput($errorCode, $data)
        );
    }

    /**
     * @return array<mixed>
     */
    public static function createErrorOutputDataProvider(): array
    {
        return [
            'empty' => [
                'errorCode' => 'error-code-value',
                'data' => [],
                'expected' => json_encode([
                    'error-code' => 'error-code-value',
                    'status' => 'error',
                ]),
            ],
            'non-empty' => [
                'errorCode' => 'error-code-value',
                'data' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
                'expected' => json_encode([
                    'error-code' => 'error-code-value',
                    'status' => 'error',
                    'key1' => 'value1',
                    'key2' => 'value2',
                ]),
            ],
        ];
    }
}
