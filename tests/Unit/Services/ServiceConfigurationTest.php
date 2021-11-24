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

        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::ENV_VAR_FILENAME);

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

        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::ENV_VAR_FILENAME);

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
        string $fileContent,
        EnvironmentVariableList $expectedEnvironmentVariables
    ): void {
        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::ENV_VAR_FILENAME);

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
            ->andReturn($fileContent)
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
                'fileContent' => '{}',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([]),
            ],
            'content not a json array' => [
                'serviceId' => 'service1',
                'fileContent' => 'true',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([]),
            ],
            'single invalid item, key not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{0:"value1"}',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([]),
            ],
            'single invalid item, value not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{"key1":true}',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([]),
            ],
            'single' => [
                'serviceId' => 'service2',
                'fileContent' => '{"key1":"value1"}',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([
                    'key1=value1',
                ]),
            ],
            'multiple' => [
                'serviceId' => 'service3',
                'fileContent' => '{"key1":"value1", "key2":"value2"}',
                'expectedEnvironmentVariables' => new EnvironmentVariableList([
                    'key1=value1',
                    'key2=value2',
                ]),
            ],
        ];
    }

    public function testGetHealthCheckUrlFileDoesNotExist(): void
    {
        $serviceId = 'service_id';

        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME);

        $mockNamespace = 'App\Services';

        PHPMockery::mock($mockNamespace, 'file_exists')
            ->with($expectedFilePath)
            ->andReturn(false)
        ;

        $healthCheckUrl = $this->serviceConfiguration->getHealthCheckUrl($serviceId);

        self::assertNull($healthCheckUrl);
    }

    public function testGetHealthCheckUrlFileIsNotReadable(): void
    {
        $serviceId = 'service_id';

        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME);

        $mockNamespace = 'App\Services';

        PHPMockery::mock($mockNamespace, 'file_exists')
            ->with($expectedFilePath)
            ->andReturn(true)
        ;

        PHPMockery::mock($mockNamespace, 'is_readable')
            ->with($expectedFilePath)
            ->andReturn(false)
        ;

        $healthCheckUrl = $this->serviceConfiguration->getHealthCheckUrl($serviceId);

        self::assertNull($healthCheckUrl);
    }

    /**
     * @dataProvider getHealthCheckUrlSuccessDataProvider
     */
    public function testGetHealthCheckUrlSuccess(
        string $serviceId,
        string $fileContent,
        ?string $expectedHealthCheckUrl
    ): void {
        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME);

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
            ->andReturn($fileContent)
        ;

        $healthCheckUrl = $this->serviceConfiguration->getHealthCheckUrl($serviceId);

        self::assertEquals($expectedHealthCheckUrl, $healthCheckUrl);
    }

    /**
     * @return array<mixed>
     */
    public function getHealthCheckUrlSuccessDataProvider(): array
    {
        return [
            'empty' => [
                'serviceId' => 'service1',
                'fileContent' => '{}',
                'expectedHealthCheckUrl' => null,
            ],
            'content not a json array' => [
                'serviceId' => 'service1',
                'fileContent' => 'true',
                'expectedHealthCheckUrl' => null,
            ],
            'single invalid item, key not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{0:"value1"}',
                'expectedHealthCheckUrl' => null,
            ],
            'single invalid item, value not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{"key1":true}',
                'expectedHealthCheckUrl' => null,
            ],
            'invalid, not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{"health_check_url":true}',
                'expectedHealthCheckUrl' => null,
            ],
            'valid' => [
                'serviceId' => 'service2',
                'fileContent' => '{"health_check_url":"http://example.com/health-check"}',
                'expectedHealthCheckUrl' => 'http://example.com/health-check',
            ],
        ];
    }

    private function createExpectedDataFilePath(string $serviceId, string $filename): string
    {
        return sprintf(
            '%s/%s/%s',
            self::SERVICE_CONFIGURATION_DIRECTORY,
            $serviceId,
            $filename
        );
    }
}
