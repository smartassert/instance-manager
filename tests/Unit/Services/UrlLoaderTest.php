<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\Filename;
use App\Enum\UrlKey;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Services\ServiceConfigurationOperator;
use App\Services\UrlLoader;
use App\Tests\Model\ExpectedFilePath;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class UrlLoaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const FILENAME = Filename::URL_COLLECTION->value;

    public function testLoadFileIsNotReadable(): void
    {
        $serviceId = md5((string) rand());
        $expectedFilePath = ExpectedFilePath::create($serviceId, self::FILENAME);

        $this->expectExceptionObject(new ServiceConfigurationMissingException($serviceId, self::FILENAME));

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with($expectedFilePath)
            ->andThrow(UnableToReadFile::fromLocation($expectedFilePath))
        ;

        $loader = new UrlLoader(
            new ServiceConfigurationOperator($filesystem)
        );

        $loader->load($serviceId, UrlKey::HEALTH_CHECK);
    }

    /**
     * @dataProvider loadHealthCheckUrlValueMissingDataProvider
     */
    public function testLoadValueMissing(
        string $fileContent,
        UrlKey $key,
        string $expectedExceptionKey
    ): void {
        $serviceId = md5((string) rand());
        $expectedFilePath = ExpectedFilePath::create($serviceId, self::FILENAME);

        $this->expectExceptionObject(new ConfigurationFileValueMissingException(
            Filename::URL_COLLECTION->value,
            $expectedExceptionKey,
            $serviceId
        ));

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with($expectedFilePath)
            ->andReturn($fileContent)
        ;

        $loader = new UrlLoader(
            new ServiceConfigurationOperator($filesystem)
        );
        $loader->load($serviceId, $key);
    }

    /**
     * @return array<mixed>
     */
    public function loadHealthCheckUrlValueMissingDataProvider(): array
    {
        return [
            'empty, key=health_check_url' => [
                'fileContent' => '{}',
                'key' => UrlKey::HEALTH_CHECK,
                'expectedExceptionKey' => UrlKey::HEALTH_CHECK->value,
            ],
            'empty, key=state_url' => [
                'fileContent' => '{}',
                'key' => UrlKey::STATE,
                'expectedExceptionKey' => UrlKey::STATE->value,
            ],
            'content not a json array' => [
                'fileContent' => 'true',
                'key' => UrlKey::HEALTH_CHECK,
                'expectedExceptionKey' => UrlKey::HEALTH_CHECK->value,
            ],
            'single invalid item, key not a string' => [
                'fileContent' => '{0:"value1"}',
                'key' => UrlKey::HEALTH_CHECK,
                'expectedExceptionKey' => UrlKey::HEALTH_CHECK->value,
            ],
            'single invalid item, value not a string' => [
                'fileContent' => '{"key1":true}',
                'key' => UrlKey::HEALTH_CHECK,
                'expectedExceptionKey' => UrlKey::HEALTH_CHECK->value,
            ],
            'health_check_url key present, value not a string' => [
                'fileContent' => json_encode([
                    UrlKey::HEALTH_CHECK->value => true,
                ]),
                'key' => UrlKey::HEALTH_CHECK,
                'expectedExceptionKey' => UrlKey::HEALTH_CHECK->value,
            ],
            'health_check_url valid, state_url missing' => [
                'fileContent' => json_encode([
                    UrlKey::HEALTH_CHECK->value => 'http://example.com/health',
                ]),
                'key' => UrlKey::STATE,
                'expectedExceptionKey' => UrlKey::STATE->value,
            ],
            'health_check_url valid, state_url not a string' => [
                'fileContent' => json_encode([
                    UrlKey::HEALTH_CHECK->value => 'http://example.com/health',
                    UrlKey::STATE->value => 123,
                ]),
                'key' => UrlKey::STATE,
                'expectedExceptionKey' => UrlKey::STATE->value,
            ],
        ];
    }

    public function testLoadSuccess(): void
    {
        $serviceId = md5((string) rand());

        $healthCheckUrl = 'http://example.com/' . md5((string) rand());
        $stateUrl = 'http://example.com/' . md5((string) rand());

        $filesystem = \Mockery::mock(FilesystemOperator::class);
        $filesystem
            ->shouldReceive('read')
            ->with(ExpectedFilePath::create($serviceId, self::FILENAME))
            ->andReturn(json_encode([
                UrlKey::HEALTH_CHECK->value => $healthCheckUrl,
                UrlKey::STATE->value => $stateUrl,
            ]))
        ;

        $loader = new UrlLoader(
            new ServiceConfigurationOperator($filesystem)
        );

        self::assertSame($healthCheckUrl, $loader->load($serviceId, UrlKey::HEALTH_CHECK));
        self::assertSame($stateUrl, $loader->load($serviceId, UrlKey::STATE));
    }
}
