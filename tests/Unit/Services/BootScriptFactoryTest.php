<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Model\EnvironmentVariable;
use App\Services\BootScriptFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class BootScriptFactoryTest extends TestCase
{
    /**
     * @dataProvider createDataProvider
     *
     * @param Collection<int, EnvironmentVariable> $environmentVariables
     */
    public function testCreate(Collection $environmentVariables, string $serviceBootScript, string $expected): void
    {
        $bootScriptFactory = new BootScriptFactory();

        self::assertSame(
            $expected,
            $bootScriptFactory->create($environmentVariables, $serviceBootScript)
        );
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        return [
            'empty environment variables, empty service boot script' => [
                'environmentVariables' => new ArrayCollection(),
                'serviceBootScript' => '',
                'expected' => '',
            ],
            'single environment variable, empty service boot script' => [
                'environmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('key', 'value'),
                ]),
                'serviceBootScript' => '',
                'expected' => '#!/usr/bin/env bash' . "\n" .
                    'echo \'key="value"\' >> /etc/environment',
            ],
            'multiple environment variables, empty service boot script' => [
                'environmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                    new EnvironmentVariable('key3', 'value3'),
                ]),
                'serviceBootScript' => '',
                'expected' => '#!/usr/bin/env bash' . "\n" .
                    'echo \'key1="value1"\' >> /etc/environment' . "\n" .
                    'echo \'key2="value2"\' >> /etc/environment' . "\n" .
                    'echo \'key3="value3"\' >> /etc/environment',
            ],
            'empty environment variables, has service boot script' => [
                'environmentVariables' => new ArrayCollection(),
                'serviceBootScript' => './first-boot.sh',
                'expected' => '#!/usr/bin/env bash' . "\n" .
                    './first-boot.sh',
            ],
            'multiple environment variables, has service boot script' => [
                'environmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                    new EnvironmentVariable('key3', 'value3'),
                ]),
                'serviceBootScript' => './first-boot.sh',
                'expected' => '#!/usr/bin/env bash' . "\n" .
                    'echo \'key1="value1"\' >> /etc/environment' . "\n" .
                    'echo \'key2="value2"\' >> /etc/environment' . "\n" .
                    'echo \'key3="value3"\' >> /etc/environment' . "\n" .
                    './first-boot.sh',
            ],
        ];
    }
}
