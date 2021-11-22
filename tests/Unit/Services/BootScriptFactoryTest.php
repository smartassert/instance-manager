<?php

namespace App\Tests\Unit\Services;

use App\Model\EnvironmentVariableList;
use App\Services\BootScriptFactory;
use App\Services\ServiceConfiguration;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class BootScriptFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const SERVICE_ID = 'service-id';
    private const SERVICE_SCRIPT_CALLER = './first-boot.sh';

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(
        string $serviceScriptCaller,
        EnvironmentVariableList $environmentVariables,
        string $expectedScript
    ): void {
        $serviceConfiguration = \Mockery::mock(ServiceConfiguration::class);
        $serviceConfiguration
            ->shouldReceive('getEnvironmentVariables')
            ->with(self::SERVICE_ID)
            ->andReturn($environmentVariables)
        ;

        $bootScriptFactory = new BootScriptFactory($serviceScriptCaller, $serviceConfiguration);

        self::assertSame($expectedScript, $bootScriptFactory->create(self::SERVICE_ID));
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        return [
            'no service script caller, no environment variables' => [
                'serviceScriptCaller' => '',
                'environmentVariables' => new EnvironmentVariableList([]),
                'expectedScript' => '',
            ],
            'service script caller, no environment variables' => [
                'serviceScriptCaller' => self::SERVICE_SCRIPT_CALLER,
                'environmentVariables' => new EnvironmentVariableList([]),
                'expectedScript' => self::SERVICE_SCRIPT_CALLER,
            ],
            'no service script caller, environment variables' => [
                'serviceScriptCaller' => '',
                'environmentVariables' => new EnvironmentVariableList([
                    'key1=value1',
                    'key2=one "two" three',
                    'key3=value3',
                ]),
                'expectedScript' => 'export key1="value1"' . "\n" .
                    'export key2="one \"two\" three"' . "\n" .
                    'export key3="value3"',
            ],
        ];
    }
}
