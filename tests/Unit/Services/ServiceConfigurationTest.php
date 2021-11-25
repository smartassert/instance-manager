<?php

namespace App\Tests\Unit\Services;

use App\Model\EnvironmentVariableList;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;
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

        $this->doTestFileDoesNotExist(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::ENV_VAR_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getEnvironmentVariables($serviceId);
            },
            function ($result) {
                self::assertEquals(new EnvironmentVariableList([]), $result);
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
                self::assertEquals(new EnvironmentVariableList([]), $result);
            }
        );
    }

    /**
     * @dataProvider getEnvironmentVariablesSuccessDataProvider
     */
    public function testGetEnvironmentVariablesSuccess(
        string $serviceId,
        string $fileContent,
        EnvironmentVariableList $expectedEnvironmentVariables
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

        $this->doTestFileDoesNotExist(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getHealthCheckUrl($serviceId);
            },
            function ($result) {
                self::assertNull($result);
            }
        );
    }

    public function testGetHealthCheckUrlFileIsNotReadable(): void
    {
        $serviceId = 'service_id';

        $this->doTestFileIsNotReadable(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getHealthCheckUrl($serviceId);
            },
            function ($result) {
                self::assertNull($result);
            }
        );
    }

    /**
     * @dataProvider getHealthCheckUrlSuccessDataProvider
     */
    public function testGetHealthCheckUrlSuccess(
        string $serviceId,
        string $fileContent,
        ?string $expectedHealthCheckUrl
    ): void {
        $this->createFileReadSuccessMocks(
            'App\Services',
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            $fileContent
        );

        $healthCheckUrl = $this->serviceConfiguration->getHealthCheckUrl($serviceId);

        self::assertEquals($expectedHealthCheckUrl, $healthCheckUrl);
    }

    /**
     * @return array<mixed>
     */
    public function getHealthCheckUrlSuccessDataProvider(): array
    {
        return $this->createConfigurationPropertyAccessDataProvider(ServiceConfigurationModel::KEY_HEALTH_CHECK_URL);
    }

    public function testGetStateUrlFileDoesNotExist(): void
    {
        $serviceId = 'service_id';

        $this->doTestFileDoesNotExist(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getStateUrl($serviceId);
            },
            function ($result) {
                self::assertNull($result);
            }
        );
    }

    public function testGetStateUrlFileIsNotReadable(): void
    {
        $serviceId = 'service_id';

        $this->doTestFileIsNotReadable(
            $serviceId,
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            function (string $serviceId) {
                return $this->serviceConfiguration->getStateUrl($serviceId);
            },
            function ($result) {
                self::assertNull($result);
            }
        );
    }

    /**
     * @dataProvider getStateUrlSuccessDataProvider
     */
    public function testGetStateUrlSuccess(
        string $serviceId,
        string $fileContent,
        ?string $expectedStateUrl
    ): void {
        $this->createFileReadSuccessMocks(
            'App\Services',
            $this->createExpectedDataFilePath($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME),
            $fileContent
        );

        $stateUrl = $this->serviceConfiguration->getStateUrl($serviceId);

        self::assertSame($expectedStateUrl, $stateUrl);
    }

    /**
     * @return array<mixed>
     */
    public function getStateUrlSuccessDataProvider(): array
    {
        return $this->createConfigurationPropertyAccessDataProvider(ServiceConfigurationModel::KEY_STATE_URL);
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
        ?string $expectedImageId
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
                'fileContent' => '{"image_id":"image_id_value"}',
                'expectedImageId' => 'image_id_value',
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
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, null, null),
            ],
            'content not a json array' => [
                'serviceId' => $serviceId,
                'fileContent' => 'true',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, null, null),
            ],
            'single invalid item, key not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{0:"value1"}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, null, null),
            ],
            'single invalid item, value not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{"key1":true}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, null, null),
            ],
            'state_url invalid, not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{"state_url":true,"health_check_url":"/health-check"}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, '/health-check', null),
            ],
            'health_check_url invalid, not a string' => [
                'serviceId' => $serviceId,
                'fileContent' => '{"state_url":"/state","health_check_url":true}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, null, '/state'),
            ],
            'valid' => [
                'serviceId' => $serviceId,
                'fileContent' => '{"state_url":"/state","health_check_url":"/health-check"}',
                'expectedServiceConfiguration' => new ServiceConfigurationModel($serviceId, '/health-check', '/state'),
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function createConfigurationPropertyAccessDataProvider(string $key): array
    {
        return [
            'empty' => [
                'serviceId' => 'service1',
                'fileContent' => '{}',
                'expectedStateUrl' => null,
            ],
            'content not a json array' => [
                'serviceId' => 'service1',
                'fileContent' => 'true',
                'expectedStateUrl' => null,
            ],
            'single invalid item, key not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{0:"value1"}',
                'expectedStateUrl' => null,
            ],
            'single invalid item, value not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{"key1":true}',
                'expectedStateUrl' => null,
            ],
            'invalid, not a string' => [
                'serviceId' => 'service2',
                'fileContent' => '{"' . $key . '":true}',
                'expectedStateUrl' => null,
            ],
            'valid' => [
                'serviceId' => 'service2',
                'fileContent' => '{"' . $key . '":"http://example.com/health-check"}',
                'expectedStateUrl' => 'http://example.com/health-check',
            ],
        ];
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
