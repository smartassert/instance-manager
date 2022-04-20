<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceIsReadyCommand;
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
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

class InstanceIsReadyCommandTest extends KernelTestCase
{
    use MockeryPHPUnitIntegration;

    private InstanceIsReadyCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(InstanceIsReadyCommand::class);
        \assert($command instanceof InstanceIsReadyCommand);
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
            ->withGetStateUrlCall($serviceId, 'https://{{ host }}/state')
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
    public function testRun(
        array $input,
        ServiceConfiguration $serviceConfiguration,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        $this->setCommandServiceConfiguration($serviceConfiguration);

        foreach ($httpResponseDataCollection as $fixture) {
            if (is_array($fixture)) {
                $fixture = $this->httpResponseFactory->createFromArray($fixture);
            }

            $this->mockHandler->append($fixture);
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
        $dropletHttpResponseData = HttpResponseDataFactory::createJsonResponseData([
            'droplet' => [
                'id' => $instanceId,
            ],
        ]);

        $validServiceConfiguration = (new MockServiceConfiguration())
            ->withExistsCall($serviceId, true)
            ->withGetStateUrlCall($serviceId, 'https://{{ host }}/state')
            ->getMock()
        ;

        return [
            'service configuration missing' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                ],
                'serviceConfiguration' => (new MockServiceConfiguration())
                    ->withExistsCall($serviceId, false)
                    ->getMock(),
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                ],
                'expectedReturnCode' => InstanceIsReadyCommand::EXIT_CODE_SERVICE_CONFIGURATION_MISSING,
                'expectedOutput' => 'No configuration for service "service_id"',
            ],
            'service configuration state_url missing' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                ],
                'serviceConfiguration' => (new MockServiceConfiguration())
                    ->withExistsCall($serviceId, true)
                    ->withGetStateUrlCall($serviceId, '')
                    ->getMock(),
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                ],
                'expectedReturnCode' => InstanceIsReadyCommand::EXIT_CODE_SERVICE_STATE_URL_MISSING,
                'expectedOutput' => 'No state_url for service "service_id"',
            ],
            'instance id invalid, missing' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                ],
                'serviceConfiguration' => $validServiceConfiguration,
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
                    '--id' => 'not-numeric',
                ],
                'serviceConfiguration' => $validServiceConfiguration,
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
                    '--id' => (string) $instanceId,
                ],
                'serviceConfiguration' => $validServiceConfiguration,
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
            'no explicit readiness state' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                ],
                'serviceConfiguration' => $validServiceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => 'ready',
            ],
            'ready=false, retry-limit=1' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                    '--retry-limit' => 1,
                    '--retry-delay' => 0,
                ],
                'serviceConfiguration' => $validServiceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => false,
                    ]),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'not-ready',
            ],
            'ready=false, ready=false, retry-limit=2' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'serviceConfiguration' => $validServiceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => false,
                    ]),
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => false,
                    ]),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'not-ready' . "\n" . 'not-ready',
            ],
            'ready=false, exception, retry-limit=2' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'serviceConfiguration' => $validServiceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => false,
                    ]),
                    new \RuntimeException('exception message content'),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'not-ready' . "\n" . 'exception message content',
            ],
            'ready=true' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                ],
                'serviceConfiguration' => $validServiceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => true,
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => 'ready',
            ],
            'ready=false, ready=true, retry-limit=2' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'serviceConfiguration' => $validServiceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => false,
                    ]),
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => true,
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => 'not-ready' . "\n" . 'ready',
            ],
            'ready=<non-boolean>' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                ],
                'serviceConfiguration' => $validServiceConfiguration,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => 'non-boolean value',
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => 'ready',
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
