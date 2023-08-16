<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

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

    private function createServiceConfiguration(FilesystemOperator $filesystem): ServiceConfiguration
    {
        return new ServiceConfiguration(self::SERVICE_CONFIGURATION_DIRECTORY, $filesystem);
    }
}
