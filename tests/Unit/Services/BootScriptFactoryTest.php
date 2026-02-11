<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableCollection;
use App\Services\BootScriptFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BootScriptFactoryTest extends TestCase
{
    private BootScriptFactory $bootScriptFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootScriptFactory = new BootScriptFactory();
    }

    #[DataProvider('createDataProvider')]
    public function testCreate(
        EnvironmentVariableCollection $environmentVariables,
        string $serviceBootScript,
        string $expected
    ): void {
        self::assertSame(
            $expected,
            $this->bootScriptFactory->create($environmentVariables, $serviceBootScript)
        );
    }

    /**
     * @return array<mixed>
     */
    public static function createDataProvider(): array
    {
        return [
            'empty environment variables, empty service boot script' => [
                'environmentVariables' => new EnvironmentVariableCollection(),
                'serviceBootScript' => '',
                'expected' => '',
            ],
            'single environment variable, empty service boot script' => [
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key', 'value'),
                ]),
                'serviceBootScript' => '',
                'expected' => '#!/usr/bin/env bash' . "\n"
                    . 'export key="value"',
            ],
            'multiple environment variables, empty service boot script' => [
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                    new EnvironmentVariable('key3', 'value3'),
                ]),
                'serviceBootScript' => '',
                'expected' => '#!/usr/bin/env bash' . "\n"
                    . 'export key1="value1"' . "\n"
                    . 'export key2="value2"' . "\n"
                    . 'export key3="value3"',
            ],
            'empty environment variables, has service boot script' => [
                'environmentVariables' => new EnvironmentVariableCollection(),
                'serviceBootScript' => './first-boot.sh',
                'expected' => '#!/usr/bin/env bash' . "\n"
                    . './first-boot.sh',
            ],
            'multiple environment variables, has service boot script' => [
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                    new EnvironmentVariable('key3', 'value3'),
                ]),
                'serviceBootScript' => './first-boot.sh',
                'expected' => '#!/usr/bin/env bash' . "\n"
                    . 'export key1="value1"' . "\n"
                    . 'export key2="value2"' . "\n"
                    . 'export key3="value3"' . "\n"
                    . './first-boot.sh',
            ],
        ];
    }

    #[DataProvider('validateDataProvider')]
    public function testValidate(string $script, bool $expected): void
    {
        self::assertSame($expected, $this->bootScriptFactory->validate($script));
    }

    /**
     * @return array<mixed>
     */
    public static function validateDataProvider(): array
    {
        return [
            'empty is valid' => [
                'script' => '',
                'expected' => true,
            ],
            'whitespace only is valid' => [
                'script' => "\n\t \t\n",
                'expected' => true,
            ],
            'first line is shebang is valid' => [
                'script' => '#!/path/to/interpreter optional-arg',
                'expected' => true,
            ],
            'first line is statement is not valid' => [
                'script' => './executable.sh',
                'expected' => false,
            ],
            'first line is comment is not valid' => [
                'script' => '# This is a comment' . "\n" . '#!/path/to/interpreter optional-arg',
                'expected' => false,
            ],
        ];
    }
}
