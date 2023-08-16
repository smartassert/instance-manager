<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\Filename;
use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableCollection;
use App\Services\EnvironmentVariableCollectionLoader;
use App\Services\ServiceConfigurationLoader;
use App\Tests\Model\ExpectedFilePath;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class EnvironmentVariableCollectionLoaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const CONFIGURATION_DIRECTORY = './services';
    private const FILENAME = Filename::ENVIRONMENT_VARIABLES->value;

    public function testLoadFileIsNotReadable(): void
    {
        $serviceId = md5((string) rand());
        $expectedFilePath = ExpectedFilePath::create(self::CONFIGURATION_DIRECTORY, $serviceId, self::FILENAME);

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with($expectedFilePath)
            ->andThrow(
                UnableToReadFile::fromLocation($expectedFilePath)
            )
        ;

        $serviceConfigurationLoader = new ServiceConfigurationLoader(self::CONFIGURATION_DIRECTORY, $filesystem);
        $loader = new EnvironmentVariableCollectionLoader($serviceConfigurationLoader);

        self::assertEquals(new EnvironmentVariableCollection([]), $loader->load($serviceId));
    }

    /**
     * @dataProvider loadSuccessDataProvider
     */
    public function testLoadSuccess(string $fileContent, EnvironmentVariableCollection $expected): void
    {
        $serviceId = md5((string) rand());
        $expectedFilePath = ExpectedFilePath::create(self::CONFIGURATION_DIRECTORY, $serviceId, self::FILENAME);

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with($expectedFilePath)
            ->andReturn($fileContent)
        ;

        $serviceConfigurationLoader = new ServiceConfigurationLoader(self::CONFIGURATION_DIRECTORY, $filesystem);
        $loader = new EnvironmentVariableCollectionLoader($serviceConfigurationLoader);

        self::assertEquals($expected, $loader->load($serviceId));
    }

    /**
     * @return array<mixed>
     */
    public function loadSuccessDataProvider(): array
    {
        return [
            'empty' => [
                'fileContent' => '{}',
                'expected' => new EnvironmentVariableCollection([]),
            ],
            'content not a json array' => [
                'fileContent' => 'true',
                'expected' => new EnvironmentVariableCollection([]),
            ],
            'single invalid item, key not a string' => [
                'fileContent' => '{0:"value1"}',
                'expected' => new EnvironmentVariableCollection([]),
            ],
            'single invalid item, value not a string' => [
                'fileContent' => '{"key1":true}',
                'expected' => new EnvironmentVariableCollection([]),
            ],
            'single' => [
                'fileContent' => '{"key1":"value1"}',
                'expected' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'value1'),
                ]),
            ],
            'multiple' => [
                'fileContent' => '{"key1":"value1", "key2":"value2"}',
                'expected' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                ]),
            ]
        ];
    }
}
