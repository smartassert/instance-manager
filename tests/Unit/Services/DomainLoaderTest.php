<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\Filename;
use App\Exception\ServiceConfigurationMissingException;
use App\Services\DomainLoader;
use App\Services\ServiceConfigurationLoader;
use App\Tests\Model\ExpectedFilePath;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DomainLoaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const CONFIGURATION_DIRECTORY = './services';
    private const DEFAULT_DOMAIN = 'localhost';

    public function testGetDomainFileIsNotReadable(): void
    {
        $serviceId = md5((string) rand());
        $expectedFilePath = ExpectedFilePath::create(
            self::CONFIGURATION_DIRECTORY,
            $serviceId,
            Filename::DOMAIN->value
        );

        $this->expectExceptionObject(new ServiceConfigurationMissingException($serviceId, Filename::DOMAIN->value));

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with($expectedFilePath)
            ->andThrow(
                UnableToReadFile::fromLocation($expectedFilePath)
            )
        ;

        $serviceConfigurationLoader = new ServiceConfigurationLoader(self::CONFIGURATION_DIRECTORY, $filesystem);
        $loader = new DomainLoader($serviceConfigurationLoader, self::DEFAULT_DOMAIN);

        $loader->load($serviceId);
    }

    /**
     * @dataProvider getDomainSuccessDataProvider
     */
    public function testGetDomainSuccess(string $fileContent, string $expected): void
    {
        $serviceId = md5((string) rand());
        $expectedFilePath = ExpectedFilePath::create(
            self::CONFIGURATION_DIRECTORY,
            $serviceId,
            Filename::DOMAIN->value
        );

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with($expectedFilePath)
            ->andReturn($fileContent)
        ;

        $serviceConfigurationLoader = new ServiceConfigurationLoader(self::CONFIGURATION_DIRECTORY, $filesystem);
        $loader = new DomainLoader($serviceConfigurationLoader, self::DEFAULT_DOMAIN);

        self::assertSame($expected, $loader->load($serviceId));
    }

    /**
     * @return array<mixed>
     */
    public function getDomainSuccessDataProvider(): array
    {
        return [
            'empty' => [
                'fileContent' => '{}',
                'expected' => self::DEFAULT_DOMAIN,
            ],
            'content not a json array' => [
                'fileContent' => 'true',
                'expected' => self::DEFAULT_DOMAIN,
            ],
            'single invalid item, key not a string' => [
                'fileContent' => '{0:"value1"}',
                'expected' => self::DEFAULT_DOMAIN,
            ],
            'single invalid item, value not a string' => [
                'fileContent' => '{"key1":true}',
                'expected' => self::DEFAULT_DOMAIN,
            ],
            'file content $key invalid, not a string' => [
                'fileContent' => '{"DOMAIN":true}',
                'expected' => self::DEFAULT_DOMAIN,
            ],
            'valid' => [
                'fileContent' => '{"DOMAIN":"example.com"}',
                'expected' => 'example.com',
            ]
        ];
    }
}
