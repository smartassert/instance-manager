<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableCollection;
use App\Services\ServiceConfiguration;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ServiceConfigurationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const SERVICE_CONFIGURATION_DIRECTORY = './services';

    public function testGetEnvironmentVariablesFileIsNotReadable(): void
    {
        $serviceId = md5((string) rand());
        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::ENV_VAR_FILENAME);

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with($expectedFilePath)
            ->andThrow(
                UnableToReadFile::fromLocation($expectedFilePath)
            )
        ;

        $serviceConfiguration = $this->createServiceConfiguration($filesystem);

        self::assertEquals(
            new EnvironmentVariableCollection(),
            $serviceConfiguration->getEnvironmentVariables($serviceId)
        );
    }

    /**
     * @dataProvider getEnvironmentVariablesSuccessDataProvider
     */
    public function testGetEnvironmentVariablesSuccess(
        string $serviceId,
        string $fileContent,
        EnvironmentVariableCollection $expected
    ): void {
        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with($this->createExpectedDataFilePath($serviceId, ServiceConfiguration::ENV_VAR_FILENAME))
            ->andReturn($fileContent)
        ;

        $serviceConfiguration = $this->createServiceConfiguration($filesystem);

        $environmentVariableList = $serviceConfiguration->getEnvironmentVariables($serviceId);

        self::assertEquals($expected, $environmentVariableList);
    }

    /**
     * @return array<mixed>
     */
    public function getEnvironmentVariablesSuccessDataProvider(): array
    {
        return array_merge(
            $this->createValueMissingDataProvider('key', new EnvironmentVariableCollection()),
            [
                'single' => [
                    'serviceId' => 'service2',
                    'fileContent' => '{"key1":"value1"}',
                    'expected' => new EnvironmentVariableCollection([
                        new EnvironmentVariable('key1', 'value1'),
                    ]),
                ],
                'multiple' => [
                    'serviceId' => 'service3',
                    'fileContent' => '{"key1":"value1", "key2":"value2"}',
                    'expected' => new EnvironmentVariableCollection([
                        new EnvironmentVariable('key1', 'value1'),
                        new EnvironmentVariable('key2', 'value2'),
                    ]),
                ]
            ]
        );
    }

    public function testSetConfigurationWriteFailureUnableToCreateDirectory(): void
    {
        $serviceId = md5((string) rand());
        $dataDirectoryPath = $this->createExpectedDataDirectoryPath($serviceId);

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('directoryExists')
            ->with($dataDirectoryPath)
            ->andReturn(false)
        ;

        $filesystem
            ->shouldReceive('createDirectory')
            ->with($dataDirectoryPath)
            ->andThrow(
                new UnableToCreateDirectory($dataDirectoryPath)
            )
        ;

        $serviceConfiguration = $this->createServiceConfiguration($filesystem);

        self::assertFalse($serviceConfiguration->setServiceConfiguration($serviceId, '', ''));
    }

    public function testSetConfigurationWriteFailureUnableToWriteToFile(): void
    {
        $serviceId = md5((string) rand());
        $healthCheckUrl = '/health-check';
        $stateUrl = '/state';

        $dataDirectoryPath = $this->createExpectedDataDirectoryPath($serviceId);
        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME);

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('directoryExists')
            ->with($dataDirectoryPath)
            ->andReturn(true)
        ;

        $serviceConfigurationData = ['health_check_url' => $healthCheckUrl, 'state_url' => $stateUrl];

        $filesystem
            ->shouldReceive('write')
            ->with(
                $expectedFilePath,
                (string) json_encode($serviceConfigurationData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            )
            ->andThrow(
                UnableToWriteFile::atLocation($expectedFilePath)
            )
        ;

        $serviceConfiguration = $this->createServiceConfiguration($filesystem);

        self::assertFalse($serviceConfiguration->setServiceConfiguration($serviceId, $healthCheckUrl, $stateUrl));
    }

    public function testSetConfigurationSuccess(): void
    {
        $serviceId = md5((string) rand());
        $healthCheckUrl = '/health-check';
        $stateUrl = '/state';

        $dataDirectoryPath = $this->createExpectedDataDirectoryPath($serviceId);
        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME);

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('directoryExists')
            ->with($dataDirectoryPath)
            ->andReturn(true)
        ;

        $serviceConfigurationData = ['health_check_url' => $healthCheckUrl, 'state_url' => $stateUrl];

        $filesystem
            ->shouldReceive('write')
            ->with(
                $expectedFilePath,
                (string) json_encode($serviceConfigurationData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            )
        ;

        $serviceConfiguration = $this->createServiceConfiguration($filesystem);

        self::assertTrue($serviceConfiguration->setServiceConfiguration($serviceId, $healthCheckUrl, $stateUrl));
    }

    private function createExpectedDataDirectoryPath(string $serviceId): string
    {
        return sprintf(
            '%s/%s',
            self::SERVICE_CONFIGURATION_DIRECTORY,
            $serviceId
        );
    }

    private function createExpectedDataFilePath(string $serviceId, string $filename): string
    {
        return sprintf(
            '%s/%s',
            $this->createExpectedDataDirectoryPath($serviceId),
            $filename
        );
    }

    /**
     * @return array<mixed>
     */
    private function createValueMissingDataProvider(string $key, mixed $expected): array
    {
        $serviceId = md5((string) rand());

        return [
            'empty' => [
                'serviceId' => $serviceId,
                'fileContent' => '{}',
                'expected' => $expected,
            ],
            'content not a json array' => [
                'serviceId' => $serviceId,
                'fileContent' => 'true',
                'expected' => $expected,
            ],
            'single invalid item, key not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{0:"value1"}',
                'expected' => $expected,
            ],
            'single invalid item, value not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{"key1":true}',
                'expected' => $expected,
            ],
            'file content $key invalid, not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{"' . $key . '":true}',
                'expected' => $expected,
            ],
        ];
    }

    private function createServiceConfiguration(FilesystemOperator $filesystem): ServiceConfiguration
    {
        return new ServiceConfiguration(self::SERVICE_CONFIGURATION_DIRECTORY, $filesystem);
    }
}
