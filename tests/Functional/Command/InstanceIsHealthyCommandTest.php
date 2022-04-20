<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceIsHealthyCommand;
use App\Command\Option;
use App\Exception\ServiceIdMissingException;
use App\Services\CommandInstanceRepository;
use App\Services\ServiceConfiguration;
use App\Tests\Mock\MockServiceConfiguration;
use App\Tests\Services\HttpResponseDataFactory;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
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

    public function testRunInvalidApiToken(): void
    {
        $serviceId = 'service_id';

        $this->setCommandServiceConfiguration((new MockServiceConfiguration())
            ->withExistsCall($serviceId, true)
            ->getMock());

        $this->mockHandler->append(new Response(401));

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unauthorized');
        self::expectExceptionCode(401);

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => $serviceId,
                '--' . Option::OPTION_ID => '123',
            ]),
            new BufferedOutput()
        );
    }

    /**
     * @dataProvider runInvalidInputDataProvider
     *
     * @param array<mixed>             $input
     * @param array<int, array<mixed>> $httpResponseDataCollection
     */
    public function testRunInvalidInput(
        array $input,
        ServiceConfiguration $serviceConfiguration,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        $this->setCommandServiceConfiguration($serviceConfiguration);

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
        $instanceId = 123;

        return [
            'service configuration missing' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => (string) $instanceId,
                ],
                'serviceConfiguration' => (new MockServiceConfiguration())
                    ->withExistsCall($serviceId, false)
                    ->getMock(),
                'httpResponseDataCollection' => [],
                'expectedReturnCode' => InstanceIsHealthyCommand::EXIT_CODE_SERVICE_CONFIGURATION_MISSING,
                'expectedOutput' => 'No configuration for service "service_id"',
            ],
            'instance id invalid, missing' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                ],
                'serviceConfiguration' => (new MockServiceConfiguration())
                    ->withExistsCall($serviceId, true)
                    ->getMock(),
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
                'serviceConfiguration' => (new MockServiceConfiguration())
                    ->withExistsCall($serviceId, true)
                    ->getMock(),
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
                'serviceConfiguration' => (new MockServiceConfiguration())
                    ->withExistsCall($serviceId, true)
                    ->getMock(),
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
        ServiceConfiguration $serviceConfiguration,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        $this->setCommandServiceConfiguration($serviceConfiguration);

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
        $instanceId = 123;

        $validServiceConfiguration = (new MockServiceConfiguration())
            ->withExistsCall($serviceId, true)
            ->withGetHealthCheckUrlCall($serviceId, 'https://{{ host }}/health-check')
            ->getMock()
        ;

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
                'serviceConfiguration' => (new MockServiceConfiguration())
                    ->withExistsCall($serviceId, true)
                    ->withGetHealthCheckUrlCall($serviceId, '')
                    ->getMock(),
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
                'serviceConfiguration' => $validServiceConfiguration,
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
                'serviceConfiguration' => $validServiceConfiguration,
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
                'serviceConfiguration' => $validServiceConfiguration,
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
                'serviceConfiguration' => $validServiceConfiguration,
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
                'serviceConfiguration' => $validServiceConfiguration,
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

    private function setCommandServiceConfiguration(ServiceConfiguration $serviceConfiguration): void
    {
        ObjectReflector::setProperty(
            $this->command,
            $this->command::class,
            'serviceConfiguration',
            $serviceConfiguration
        );
    }
}
