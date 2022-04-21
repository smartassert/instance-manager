<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceIsHealthyCommand;
use App\Command\Option;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;
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
    use MissingServiceIdTestTrait;
    use MissingInstanceIdTestTrait;
    use MissingInstanceTestTrait;

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

    public function testRunWithoutServiceConfigurationFileThrowsException(): void
    {
        $serviceId = 'service_id';
        $instanceId = '123';

        $this->expectExceptionObject(
            new ServiceConfigurationMissingException($serviceId, ServiceConfiguration::CONFIGURATION_FILENAME)
        );

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => $serviceId,
                '--' . Option::OPTION_ID => $instanceId,
            ]),
            new NullOutput()
        );
    }

    public function testRunWithoutHealthCheckUrlThrowsException(): void
    {
        $serviceId = 'service_id';
        $instanceId = '123';

        $exception = new ConfigurationFileValueMissingException(
            ServiceConfiguration::CONFIGURATION_FILENAME,
            'health_check_url',
            'service_id'
        );

        ObjectReflector::setProperty(
            $this->command,
            $this->command::class,
            'serviceConfiguration',
            (new MockServiceConfiguration())
                ->withExistsCall($serviceId, true)
                ->withGetHealthCheckUrlCall($serviceId, $exception)
                ->getMock()
        );

        $this->expectExceptionObject($exception);

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => $serviceId,
                '--' . Option::OPTION_ID => $instanceId,
            ]),
            new NullOutput()
        );
    }

    public function testRunInvalidApiToken(): void
    {
        $serviceId = 'service_id';

        $this->setCommandServiceConfiguration((new MockServiceConfiguration())
            ->withExistsCall($serviceId, true)
            ->withGetHealthCheckUrlCall($serviceId, '/health-check')
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

    /**
     * @return array<mixed>
     */
    protected static function getInputExcludingInstanceId(): array
    {
        return [
            '--' . Option::OPTION_SERVICE_ID => 'service_id',
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
