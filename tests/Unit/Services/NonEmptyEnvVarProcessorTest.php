<?php

namespace App\Tests\Unit\Services;

use App\Exception\EmptyEnvironmentVariableException;
use App\Services\NonEmptyEnvVarProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class NonEmptyEnvVarProcessorTest extends TestCase
{
    private NonEmptyEnvVarProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new NonEmptyEnvVarProcessor();
    }

    public function testIsEnvVarProcessor(): void
    {
        self::assertInstanceOf(EnvVarProcessorInterface::class, $this->processor);
    }

    /**
     * @dataProvider getEnvReturnsValueDataProvider
     */
    public function testGetEnvReturnsValue(\Closure $getEnv, mixed $expected): void
    {
        self::assertSame(
            $expected,
            $this->processor->getEnv('not-applicable', 'env-var-name', $getEnv)
        );
    }

    /**
     * @return array<mixed>
     */
    public function getEnvReturnsValueDataProvider(): array
    {
        return [
            'boolean' => [
                'getEnv' => function (): bool {
                    return true;
                },
                'expected' => true,
            ],
            'integer' => [
                'getEnv' => function (): int {
                    return 17;
                },
                'expected' => 17,
            ],
            'non-empty string' => [
                'getEnv' => function (): string {
                    return 'non-empty string';
                },
                'expected' => 'non-empty string',
            ],
            'non-empty string with leading and trailing whitespace' => [
                'getEnv' => function (): string {
                    return ' non-empty string  ';
                },
                'expected' => ' non-empty string  ',
            ],
        ];
    }

    /**
     * @dataProvider getEnvThrowsExceptionDataProvider
     */
    public function testGetEnvThrowsException(\Closure $getEnv): void
    {
        $envVarName = 'env-var-name';

        self::expectException(EmptyEnvironmentVariableException::class);
        self::expectExceptionMessage(sprintf(
            'Environment variable "%s" is not allowed to be empty',
            $envVarName,
        ));

        $this->processor->getEnv('not-applicable', $envVarName, $getEnv);
    }

    /**
     * @return array<mixed>
     */
    public function getEnvThrowsExceptionDataProvider(): array
    {
        return [
            'empty' => [
                'getEnv' => function (): string {
                    return '';
                },
            ],
            'empty-when-trimmed' => [
                'getEnv' => function (): string {
                    return ' ';
                },
            ],
        ];
    }
}
