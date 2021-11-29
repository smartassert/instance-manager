<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Model\EnvironmentVariable;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;
use App\Services\ServiceConfiguration;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

        $this->doTestFileDoesNotExist(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::ENV_VAR_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getEnvironmentVariables($serviceId);
            },
            function ($result) {
                self::assertEquals(new ArrayCollection(), $result);
            }
        );
    }

    public function testGetEnvironmentVariablesFileIsNotReadable(): void
    {
        $serviceId = 'service_id';

        $this->doTestFileIsNotReadable(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::ENV_VAR_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getEnvironmentVariables($serviceId);
            },
            function ($result) {
                self::assertEquals(new ArrayCollection(), $result);
            }
        );
    }

    /**
     * @dataProvider getEnvironmentVariablesSuccessDataProvider
     *
     * @param Collection<int, EnvironmentVariable> $expectedEnvironmentVariables
     */
    public function testGetEnvironmentVariablesSuccess(
        string $serviceId,
        string $fileContent,
        Collection $expectedEnvironmentVariables
    ): void {
        $this->createFileReadSuccessMocks(
            'App\Services',
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::ENV_VAR_FILENAME),
            $fileContent
        );

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
                'expectedEnvironmentVariables' => new ArrayCollection(),
            ],
            'content not a json array' => [
                'serviceId' => 'service1',
                'fileContent' => 'true',
                'expectedEnvironmentVariables' => new ArrayCollection(),
            ],
            'single invalid item, key not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{0:"value1"}',
                'expectedEnvironmentVariables' => new ArrayCollection(),
            ],
            'single invalid item, value not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{"key1":true}',
                'expectedEnvironmentVariables' => new ArrayCollection(),
            ],
            'single' => [
                'serviceId' => 'service2',
                'fileContent' => '{"key1":"value1"}',
                'expectedEnvironmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('key1', 'value1'),
                ]),
            ],
            'multiple' => [
                'serviceId' => 'service3',
                'fileContent' => '{"key1":"value1", "key2":"value2"}',
                'expectedEnvironmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                ]),
            ],
        ];
    }

    public function testExistsDoesNotExist(): void
    {
        $serviceId = 'service_id';

        $this->doTestFileDoesNotExist(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->exists($serviceId);
            },
            function ($result) {
                self::assertFalse($result);
            }
        );
    }

    public function testExistsIsNotReadable(): void
    {
        $serviceId = 'service_id';

        $this->doTestFileIsNotReadable(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->exists($serviceId);
            },
            function ($result) {
                self::assertFalse($result);
            }
        );
    }

    public function testExistsDoesExist(): void
    {
        $serviceId = 'service_id';

        $this->createFileReadSuccessMocks(
            'App\Services',
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            ''
        );

        self::assertTrue($this->serviceConfiguration->exists($serviceId));
    }

    public function testGetImageIdFileDoesNotExist(): void
    {
        $serviceId = 'service_id';

        $this->doTestFileDoesNotExist(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::IMAGE_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getImageId($serviceId);
            },
            function ($result) {
                self::assertNull($result);
            }
        );
    }

    public function testGetImageIdFileIsNotReadable(): void
    {
        $serviceId = 'service_id';

        $this->doTestFileIsNotReadable(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::IMAGE_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getImageId($serviceId);
            },
            function ($result) {
                self::assertNull($result);
            }
        );
    }

    /**
     * @dataProvider getImageIdSuccessDataProvider
     */
    public function testGetImageIdSuccess(
        string $serviceId,
        string $fileContent,
        ?int $expectedImageId
    ): void {
        $this->createFileReadSuccessMocks(
            'App\Services',
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::IMAGE_FILENAME),
            $fileContent
        );

        self::assertEquals($expectedImageId, $this->serviceConfiguration->getImageId($serviceId));
    }

    /**
     * @return array<mixed>
     */
    public function getImageIdSuccessDataProvider(): array
    {
        return [
            'empty' => [
                'serviceId' => 'service1',
                'fileContent' => '{}',
                'expectedImageId' => null,
            ],
            'content not a json array' => [
                'serviceId' => 'service1',
                'fileContent' => 'true',
                'expectedImageId' => null,
            ],
            'single invalid item, key not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{0:"value1"}',
                'expectedImageId' => null,
            ],
            'single invalid item, value not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{"key1":true}',
                'expectedImageId' => null,
            ],
            'valid' => [
                'serviceId' => 'service2',
                'fileContent' => '{"image_id":"123456"}',
                'expectedImageId' => 123456,
            ],
        ];
    }

    public function testGetServiceConfigurationFileDoesNotExist(): void
    {
        $serviceId = 'service_id';

        $this->doTestFileDoesNotExist(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getServiceConfiguration($serviceId);
            },
            function ($result) {
                self::assertNull($result);
            }
        );
    }

    public function testGetServiceConfigurationFileIsNotReadable(): void
    {
        $serviceId = 'service_id';

        $this->doTestFileIsNotReadable(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getServiceConfiguration($serviceId);
            },
            function ($result) {
                self::assertNull($result);
            }
        );
    }

    /**
     * @dataProvider getServiceConfigurationSuccessDataProvider
     */
    public function testGetServiceConfigurationSuccess(
        string $serviceId,
        string $fileContent,
        ServiceConfigurationModel $expectedServiceConfiguration
    ): void {
        $this->createFileReadSuccessMocks(
            'App\Services',
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            $fileContent
        );

        self::assertEquals(
            $expectedServiceConfiguration,
            $this->serviceConfiguration->getServiceConfiguration($serviceId)
        );
    }

    /**
     * @return array<mixed>
     */
    public function getServiceConfigurationSuccessDataProvider(): array
    {
        $serviceId = 'service_id';

        return [
            'empty' => [
                'serviceId' => $serviceId,
                'fileContent' => '{}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, '', ''),
            ],
            'content not a json array' => [
                'serviceId' => $serviceId,
                'fileContent' => 'true',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, '', ''),
            ],
            'single invalid item, key not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{0:"value1"}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, '', ''),
            ],
            'single invalid item, value not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{"key1":true}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, '', ''),
            ],
            'state_url invalid, not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{"state_url":true,"health_check_url":"/health-check"}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, '/health-check', ''),
            ],
            'health_check_url invalid, not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{"state_url":"/state","health_check_url":true}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, '', '/state'),
            ],
            'valid' => [
                'serviceId' => $serviceId,
                'fileContent' => '{"state_url":"/state","health_check_url":"/health-check"}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, '/health-check', '/state'),
            ],
        ];
    }

    public function testSetConfigurationWriteFailureUnableToCreateDirectory(): void
    {
        $serviceId = 'service_id';
        $serviceConfiguration = new ServiceConfigurationModel($serviceId, '', '');
        $dataDirectoryPath = $this->createExpectedDataDirectoryPath($serviceId);

        $this->mockFileExistsDoesNotExist($dataDirectoryPath);
        $this->mockMkdir($dataDirectoryPath, false);

        $result = $this->serviceConfiguration->setServiceConfiguration($serviceConfiguration);

        self::assertFalse($result);
    }

    public function testSetConfigurationWriteFailureUnableToWriteToFile(): void
    {
        $serviceId = 'service_id';
        $healthCheckUrl = '/health-check';
        $stateUrl = '/state';

        $serviceConfiguration = new ServiceConfigurationModel($serviceId, $healthCheckUrl, $stateUrl);
        $dataDirectoryPath = $this->createExpectedDataDirectoryPath($serviceId);
        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME);

        $this->mockFileExistsDoesNotExist($dataDirectoryPath);
        $this->mockMkdir($dataDirectoryPath, true);
        $this->mockFilePutContents($expectedFilePath, $healthCheckUrl, $stateUrl, false);

        $result = $this->serviceConfiguration->setServiceConfiguration($serviceConfiguration);

        self::assertFalse($result);
    }

    public function testSetConfigurationSuccess(): void
    {
        $serviceId = 'service_id';
        $healthCheckUrl = '/health-check';
        $stateUrl = '/state';

        $serviceConfiguration = new ServiceConfigurationModel($serviceId, $healthCheckUrl, $stateUrl);
        $dataDirectoryPath = $this->createExpectedDataDirectoryPath($serviceId);
        $expectedFilePath = $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME);

        $this->mockFileExistsDoesNotExist($dataDirectoryPath);
        $this->mockMkdir($dataDirectoryPath, true);
        $this->mockFilePutContents($expectedFilePath, $healthCheckUrl, $stateUrl, 123);

        $result = $this->serviceConfiguration->setServiceConfiguration($serviceConfiguration);

        self::assertTrue($result);
    }

    private function createFileReadSuccessMocks(string $namespace, string $filePath, string $content): void
    {
        PHPMockery::mock($namespace, 'file_exists')
            ->with($filePath)
            ->andReturn(true)
        ;

        PHPMockery::mock($namespace, 'is_readable')
            ->with($filePath)
            ->andReturn(true)
        ;

        PHPMockery::mock($namespace, 'file_get_contents')
            ->with($filePath)
            ->andReturn($content)
        ;
    }

    private function doTestFileIsNotReadable(
        string $serviceId,
        string $expectedFilePath,
        callable $action,
        callable $assertions
    ): void {
        $mockSetup = function (string $mockNamespace, string $expectedFilePath) {
            PHPMockery::mock($mockNamespace, 'file_exists')
                ->with($expectedFilePath)
                ->andReturn(true)
            ;

            PHPMockery::mock($mockNamespace, 'is_readable')
                ->with($expectedFilePath)
                ->andReturn(false)
            ;
        };

        $this->doTestFileOperationFailure($serviceId, $expectedFilePath, $mockSetup, $action, $assertions);
    }

    private function doTestFileDoesNotExist(
        string $serviceId,
        string $expectedFilePath,
        callable $action,
        callable $assertions
    ): void {
        $mockSetup = function (string $mockNamespace, string $expectedFilePath) {
            PHPMockery::mock($mockNamespace, 'file_exists')
                ->with($expectedFilePath)
                ->andReturn(false)
            ;
        };

        $this->doTestFileOperationFailure($serviceId, $expectedFilePath, $mockSetup, $action, $assertions);
    }

    private function doTestFileOperationFailure(
        string $serviceId,
        string $expectedFilePath,
        callable $mockSetup,
        callable $action,
        callable $assertions
    ): void {
        $mockNamespace = 'App\Services';

        $mockSetup($mockNamespace, $expectedFilePath);

        $result = $action($serviceId);
        $assertions($result);
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

    private function mockFileExistsDoesNotExist(string $path): void
    {
        PHPMockery::mock('App\Services', 'file_exists')
            ->with($path)
            ->andReturn(false)
        ;
    }

    private function mockMkdir(string $path, bool $return): void
    {
        PHPMockery::mock('App\Services', 'mkdir')
            ->withArgs(function ($directory, $recursive) use ($path) {
                self::assertSame($path, $directory);
                self::assertTrue($recursive);

                return true;
            })
            ->andReturn($return)
        ;
    }

    private function mockFilePutContents(
        string $expectedFilePath,
        string $healthCheckUrl,
        string $stateUrl,
        bool|int $return
    ): void {
        PHPMockery::mock('App\Services', 'file_put_contents')
            ->withArgs(function ($filePath, $content) use ($expectedFilePath, $healthCheckUrl, $stateUrl) {
                self::assertSame($expectedFilePath, $filePath);
                self::assertSame(
                    <<<END
                    {
                        "health_check_url": "{$healthCheckUrl}",
                        "state_url": "{$stateUrl}"
                    }
                    END,
                    $content
                );

                return true;
            })
            ->andReturn($return)
        ;
    }
}
