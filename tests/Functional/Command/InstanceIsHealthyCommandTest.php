<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceIsHealthyCommand;
use App\Command\Option;
use App\Exception\ServiceIdMissingException;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;
use App\Services\CommandInstanceRepository;
use App\Services\ServiceConfiguration;
use App\Tests\Services\HttpResponseDataFactory;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

class InstanceIsHealthyCommandTest extends KernelTestCase
{
    private InstanceIsHealthyCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(InstanceIsHealthyCommand::class);
        \assert($command instanceof InstanceIsHealthyCommand);
        $this->command = $command;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    public function testRunWithoutServiceIdThrowsException(): void
    {
        $this->expectExceptionObject(new ServiceIdMissingException());

        $this->command->run(new ArrayInput([]), new NullOutput());
    }

    /**
     * @dataProvider runThrowsExceptionDataProvider
     *
     * @param array<mixed>             $httpResponseData
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    public function testRunThrowsException(
        array $httpResponseData,
        string $expectedExceptionClass,
        string $expectedExceptionMessage,
        int $expectedExceptionCode
    ): void {
        $serviceId = 'service_id';

        $this->mockServiceConfiguration(
            $serviceId,
            new ServiceConfigurationModel(
                $serviceId,
                'https://{{ host }}/health-check',
                'https://{{ host }}/state'
            )
        );

        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray($httpResponseData)
        );

        self::expectException($expectedExceptionClass);
        self::expectExceptionMessage($expectedExceptionMessage);
        self::expectExceptionCode($expectedExceptionCode);

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => $serviceId,
                '--' . Option::OPTION_ID => '123',
            ]),
            new BufferedOutput()
        );
    }

    /**
     * @return array<mixed>
     */
    public function runThrowsExceptionDataProvider(): array
    {
        return [
            'invalid api token' => [
                'httpResponseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 401,
                ],
                'expectedExceptionClass' => RuntimeException::class,
                'expectedExceptionMessage' => 'Unauthorized',
                'expectedExceptionCode' => 401,
            ],
        ];
    }

    /**
     * @dataProvider runInvalidInputDataProvider
     *
     * @param array<mixed>             $input
     * @param array<int, array<mixed>> $httpResponseDataCollection
     */
    public function testRunInvalidInput(
        array $input,
        ?ServiceConfigurationModel $serviceConfiguration,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        $serviceId = $input['--service-id'] ?? '';
        $serviceId = is_string($serviceId) ? $serviceId : '';

        $this->mockServiceConfiguration($serviceId, $serviceConfiguration);

        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->mockHandler->append(
                $this->httpResponseFactory->createFromArray($httpResponseData)
            );
        }

        $output = new BufferedOutput();

        $commandReturnCode = $this->command->run(new ArrayInput($input), $output);

        self::assertSame($expectedReturnCode, $commandReturnCode);
        self::assertSame($expectedOutput, $output->fetch());
    }

    /**
     * @return array<mixed>
     */
    public function runInvalidInputDataProvider(): array
    {
        $serviceId = 'service_id';

        $serviceConfiguration = new ServiceConfigurationModel(
            $serviceId,
            'https://{{ host }}/health-check',
            'https://{{ host }}/state'
        );

        $instanceId = 123;

        return [
            'service configuration missing' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => (string) $instanceId,
                ],
                'serviceConfiguration' => null,
                'httpResponseDataCollection' => [],
                'expectedReturnCode' => InstanceIsHealthyCommand::EXIT_CODE_SERVICE_CONFIGURATION_MISSING,
                'expectedOutput' => 'No configuration for service "service_id"',
            ],
            'instance id invalid, missing' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                ],
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => [],
                'expectedReturnCode' => CommandInstanceRepository::EXIT_CODE_ID_INVALID,
                'expectedOutput' => (string) json_encode([
                    'status' => 'error',
                    'error-code' => 'id-invalid',
                ]),
            ],
            'instance id invalid, not numeric' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => 'not-numeric',
                ],
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => [],
                'expectedReturnCode' => CommandInstanceRepository::EXIT_CODE_ID_INVALID,
                'expectedOutput' => (string) json_encode([
                    'status' => 'error',
                    'error-code' => 'id-invalid',
                ]),
            ],
            'instance not found' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => (string) $instanceId,
                ],
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => [
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 404,
                    ],
                ],
                'expectedReturnCode' => CommandInstanceRepository::EXIT_CODE_NOT_FOUND,
                'expectedOutput' => (string) json_encode([
                    'status' => 'error',
                    'error-code' => 'not-found',
                    'id' => 123,
                ]),
            ],
        ];
    }

    /**
     * @dataProvider runDataProvider
     *
     * @param array<mixed>             $input
     * @param array<int, array<mixed>> $httpResponseDataCollection
     */
    public function testRunSuccess(
        array $input,
        ?ServiceConfigurationModel $serviceConfiguration,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        $serviceId = $input['--service-id'] ?? '';
        $serviceId = is_string($serviceId) ? $serviceId : '';

        $this->mockServiceConfiguration($serviceId, $serviceConfiguration);

        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->mockHandler->append(
                $this->httpResponseFactory->createFromArray($httpResponseData)
            );
        }

        $output = new BufferedOutput();

        $commandReturnCode = $this->command->run(new ArrayInput($input), $output);

        self::assertSame($expectedReturnCode, $commandReturnCode);
        self::assertSame($expectedOutput, $output->fetch());
    }

    /**
     * @return array<mixed>
     */
    public function runDataProvider(): array
    {
        $serviceId = 'service_id';

        $serviceConfiguration = new ServiceConfigurationModel(
            $serviceId,
            'https://{{ host }}/health-check',
            'https://{{ host }}/state'
        );

        $instanceId = 123;

        $dropletResponseData = HttpResponseDataFactory::createJsonResponseData([
            'droplet' => [
                'id' => $instanceId,
            ],
        ]);

        return [
            'no health check url' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => (string) $instanceId,
                ],
                'serviceConfiguration' => new ServiceConfigurationModel(
                    $serviceId,
                    '',
                    'https://{{ host }}/state'
                ),
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => '',
            ],
            'no health data' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . Option::OPTION_ID => (string) $instanceId,
                ],
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                    HttpResponseDataFactory::createJsonResponseData([]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => '[]',
            ],
            'not healthy, retry-limit=1' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . Option::OPTION_ID => (string) $instanceId,
                    '--retry-limit' => 1,
                    '--retry-delay' => 0,
                ],
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                    HttpResponseDataFactory::createJsonResponseData(
                        [
                            'service1' => 'unavailable',
                            'service2' => 'available',
                            'service3' => 'available',
                        ],
                        503
                    ),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => (string) json_encode([
                    'service1' => 'unavailable',
                    'service2' => 'available',
                    'service3' => 'available',
                ]),
            ],
            'not healthy, not-healthy, retry-limit=2' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . Option::OPTION_ID => (string) $instanceId,
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                    HttpResponseDataFactory::createJsonResponseData(
                        [
                            'service1' => 'unavailable',
                            'service2' => 'available',
                        ],
                        503
                    ),
                    HttpResponseDataFactory::createJsonResponseData(
                        [
                            'service1' => 'unavailable',
                            'service2' => 'available',
                        ],
                        503
                    ),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => json_encode(['service1' => 'unavailable', 'service2' => 'available']) . "\n" .
                    json_encode(['service1' => 'unavailable', 'service2' => 'available']),
            ],
            'not healthy, healthy, retry-limit=2' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . Option::OPTION_ID => (string) $instanceId,
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                    HttpResponseDataFactory::createJsonResponseData(
                        [
                            'service1' => 'unavailable',
                            'service2' => 'available',
                        ],
                        503
                    ),
                    HttpResponseDataFactory::createJsonResponseData([
                        'service1' => 'available',
                        'service2' => 'available',
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => json_encode(['service1' => 'unavailable', 'service2' => 'available']) . "\n" .
                    json_encode(['service1' => 'available', 'service2' => 'available']),
            ],
            'healthy' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . Option::OPTION_ID => (string) $instanceId,
                ],
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'service1' => 'available',
                        'service2' => 'available',
                        'service3' => 'available',
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    'service1' => 'available',
                    'service2' => 'available',
                    'service3' => 'available',
                ]),
            ],
        ];
    }

    private function mockServiceConfiguration(
        string $serviceId,
        ?ServiceConfigurationModel $serviceConfigurationModel
    ): void {
        $serviceConfiguration = \Mockery::mock(ServiceConfiguration::class);
        $serviceConfiguration
            ->shouldReceive('getServiceConfiguration')
            ->with($serviceId)
            ->andReturn($serviceConfigurationModel)
        ;

        ObjectReflector::setProperty(
            $this->command,
            $this->command::class,
            'serviceConfiguration',
            $serviceConfiguration
        );
    }
}
