<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\Filename;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Services\ImageIdLoader;
use App\Services\ServiceConfigurationOperator;
use App\Tests\Model\ExpectedFilePath;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ImageIdLoaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const FILENAME = Filename::IMAGE->value;

    public function testLoadFileIsNotReadable(): void
    {
        $serviceId = md5((string) rand());
        $expectedFilePath = ExpectedFilePath::create($serviceId, self::FILENAME);

        $this->expectExceptionObject(new ServiceConfigurationMissingException($serviceId, self::FILENAME));

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with($expectedFilePath)
            ->andThrow(
                UnableToReadFile::fromLocation($expectedFilePath)
            )
        ;

        $loader = new ImageIdLoader(
            new ServiceConfigurationOperator($filesystem)
        );

        $loader->load($serviceId);
    }

    /**
     * @dataProvider loadValueMissingDataProvider
     */
    public function testLoadValueMissing(string $fileContent): void
    {
        $serviceId = md5((string) rand());

        $this->expectExceptionObject(new ConfigurationFileValueMissingException(
            self::FILENAME,
            'image_id',
            $serviceId
        ));

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with(ExpectedFilePath::create($serviceId, self::FILENAME))
            ->andReturn($fileContent)
        ;

        $loader = new ImageIdLoader(
            new ServiceConfigurationOperator($filesystem)
        );

        $loader->load($serviceId);
    }

    /**
     * @return array<mixed>
     */
    public function loadValueMissingDataProvider(): array
    {
        return [
            'empty' => [
                'fileContent' => '{}',
            ],
            'content not a json array' => [
                'fileContent' => 'true',
            ],
            'single invalid item, key not a string' => [
                'fileContent' => '{0:"value1"}',
            ],
            'single invalid item, value not a string' => [
                'fileContent' => '{"key1":true}',
            ],
            'file content $key invalid, not a string' => [
                'fileContent' => '{"image_id":true}',
            ],
        ];
    }

    public function testLoadSuccess(): void
    {
        $serviceId = md5((string) rand());
        $fileContent = '{"image_id":"123456"}';
        $expectedImageId = 123456;

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with(ExpectedFilePath::create($serviceId, self::FILENAME))
            ->andReturn($fileContent)
        ;

        $loader = new ImageIdLoader(
            new ServiceConfigurationOperator($filesystem)
        );

        $loader->load($serviceId);

        self::assertSame($expectedImageId, $loader->load($serviceId));
    }
}
