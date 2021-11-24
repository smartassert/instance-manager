<?php

namespace App\Tests\Unit\Services;

use App\Model\EnvironmentVariableList;
use App\Services\ServiceConfiguration;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use phpmock\mockery\PHPMockery;
use PHPUnit\Framework\TestCase;

class ServiceConfigurationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const SERVICE_CONFIGURATION_DIRECTORY = './services';

    private ServiceConfiguration $serviceConfiguration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceConfiguration = new ServiceConfiguration(self::SERVICE_CONFIGURATION_DIRECTORY);
    }

    public function testGetEnvironmentVariablesFileDoesNotExist(): void
    {
        $serviceId = 'service_id';

        $expectedFilePath = $this->createExpectedEnvVarFilePath($serviceId);

        $mockNamespace = 'App\Services';

        PHPMockery::mock($mockNamespace, 'file_exists')
            ->with($expectedFilePath)
            ->andReturn(false)
        ;

        $environmentVariableList = $this->serviceConfiguration->getEnvironmentVariables($serviceId);

        self::assertEquals(new EnvironmentVariableList([]), $environmentVariableList);
    }

    public function testGetEnvironmentVariablesFileIsNotReadable(): void
    {
        $serviceId = 'service_id';

        $expectedFilePath = $this->createExpectedEnvVarFilePath($serviceId);

        $mockNamespace = 'App\Services';

        PHPMockery::mock($mockNamespace, 'file_exists')
            ->with($expectedFilePath)
            ->andReturn(true)
        ;

        PHPMockery::mock($mockNamespace, 'is_readable')
            ->with($expectedFilePath)
            ->andReturn(false)
        ;

        $environmentVariableList = $this->serviceConfiguration->getEnvironmentVariables($serviceId);

        self::assertEquals(new EnvironmentVariableList([]), $environmentVariableList);
    }

    /**
     * @dataProvider getEnvironmentVariablesSuccessDataProvider
     */
    public function testGetEnvironmentVariablesSuccess(
        string $serviceId,
        string $envVarFileContent,
        EnvironmentVariableList $expectedEnvironmentVariables
    ): void {
        $expectedFilePath = $this->createExpectedEnvVarFilePath($serviceId);

        $mockNamespace = 'App\Services';

        PHPMockery::mock($mockNamespace, 'file_exists')
            ->with($expectedFilePath)
            ->andReturn(true)
        ;

        PHPMockery::mock($mockNamespace, 'is_readable')
            ->with($expectedFilePath)
            ->andReturn(true)
        ;

        PHPMockery::mock($mockNamespace, 'file_get_contents')
            ->with($expectedFilePath)
            ->andReturn($envVarFileContent)
        ;

        $environmentVariableList = $this->serviceConfiguration->getEnvironmentVariables($serviceId);

        self::assertEquals($expectedEnvironmentVariables, $environmentVariableList);
    }

    /**
     * @return array<mixed>
     */
    public function getEnvironmentVariablesSuccessDataProvider(): array
    {
        return [
            'empty' => [
                'serviceId' => 'service1',
                'envVarFileContent' => '{}',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([]),
            ],
            'content not a json array' => [
                'serviceId' => 'service1',
                'envVarFileContent' => 'true',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([]),
            ],
            'single invalid item, key not a string' => [
                'serviceId' => 'service2',
                'envVarFileContent' => '{0:"value1"}',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([]),
            ],
            'single invalid item, value not a string' => [
                'serviceId' => 'service2',
                'envVarFileContent' => '{"key1":true}',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([]),
            ],
            'single' => [
                'serviceId' => 'service2',
                'envVarFileContent' => '{"key1":"value1"}',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([
                    'key1=value1',
                ]),
            ],
            'multiple' => [
                'serviceId' => 'service3',
                'envVarFileContent' => '{"key1":"value1", "key2":"value2"}',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([
                    'key1=value1',
                    'key2=value2',
                ]),
            ],
        ];
    }

    private function createExpectedEnvVarFilePath(string $serviceId): string
    {
        return sprintf(
            '%s/%s/%s',
            self::SERVICE_CONFIGURATION_DIRECTORY,
            $serviceId,
            ServiceConfiguration::ENV_VAR_FILENAME
        );
    }
}
