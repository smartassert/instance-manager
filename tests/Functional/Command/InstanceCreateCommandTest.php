<?php

namespace App\Tests\Functional\Command;

use App\Command\InstanceCreateCommand;
use App\Command\Option;
use App\Exception\MissingSecretException;
use App\Model\EnvironmentVariableList;
use App\Services\InstanceRepository;
use App\Services\ServiceConfiguration;
use App\Tests\Services\HttpResponseFactory;
use App\Tests\Services\InstanceFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

class InstanceCreateCommandTest extends KernelTestCase
{
    private InstanceCreateCommand $command;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(InstanceCreateCommand::class);
        \assert($command instanceof InstanceCreateCommand);
        $this->command = $command;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    /**
     * @dataProvider runThrowsExceptionDataProvider
     *
     * @param array<mixed>             $responseData
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    public function testRunThrowsException(
        array $responseData,
        string $expectedExceptionClass,
        string $expectedExceptionMessage,
        int $expectedExceptionCode
    ): void {
        $httpResponse = $this->httpResponseFactory->createFromArray($responseData);
        $this->setHttpResponse($httpResponse);

        self::expectException($expectedExceptionClass);
        self::expectExceptionMessage($expectedExceptionMessage);
        self::expectExceptionCode($expectedExceptionCode);

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => 'service_id',
                '--' . Option::OPTION_IMAGE_ID => '123456',
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
                'responseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 401,
                ],
                'expectedExceptionClass' => RuntimeException::class,
                'expectedExceptionMessage' => 'Unauthorized',
                'expectedExceptionCode' => 401,
            ],
        ];
    }

    /**
     * @dataProvider runEmptyRequiredValueDataProvider
     *
     * @param array<mixed> $input
     */
    public function testRunEmptyRequiredValue(array $input, int $expectedReturnCode): void
    {
        $commandReturnCode = $this->command->run(new ArrayInput($input), new NullOutput());

        self::assertSame($expectedReturnCode, $commandReturnCode);
    }

    /**
     * @return array<mixed>
     */
    public function runEmptyRequiredValueDataProvider(): array
    {
        return [
            'empty service id' => [
                'input' => [
                    '--' . Option::OPTION_IMAGE_ID => '123456',
                ],
                'expectedReturnCode' => InstanceCreateCommand::EXIT_CODE_EMPTY_COLLECTION_TAG,
            ],
            'empty tag' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                ],
                'expectedReturnCode' => InstanceCreateCommand::EXIT_CODE_EMPTY_TAG,
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
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->setHttpResponse(
                $this->httpResponseFactory->createFromArray($httpResponseData)
            );
        }

        $output = new BufferedOutput();

        $commandReturnCode = $this->command->run(new ArrayInput($input), $output);

        self::assertSame($expectedReturnCode, $commandReturnCode);
        self::assertJsonStringEqualsJsonString($expectedOutput, $output->fetch());
    }

    /**
     * @return array<mixed>
     */
    public function runDataProvider(): array
    {
        return [
            'already exists' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . Option::OPTION_IMAGE_ID => '123456',
                ],
                'httpResponseDataCollection' => [
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                [
                                    'id' => 123,
                                ]
                            ],
                        ]),
                    ],
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    'status' => 'success',
                    'id' => 123,
                ]),
            ],
            'created' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . Option::OPTION_IMAGE_ID => '123456',
                ],
                'httpResponseDataCollection' => [
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [],
                        ]),
                    ],
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplet' => [
                                'id' => 789,
                            ],
                        ]),
                    ],
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    'status' => 'success',
                    'id' => 789,
                ]),
            ],
        ];
    }

    /**
     * @dataProvider passesFirstBootScriptDataProvider
     */
    public function testPassesFirstBootScript(
        string $firstBootScriptOption,
        string $secretsJsonOption,
        EnvironmentVariableList $environmentVariableList,
        string $expectedFirstBootScript,
    ): void {
        $serviceId = 'service_id';
        $imageId = 'image-id';

        $input = new ArrayInput([
            '--' . Option::OPTION_SERVICE_ID => $serviceId,
            '--' . Option::OPTION_IMAGE_ID => $imageId,
            '--' . InstanceCreateCommand::OPTION_FIRST_BOOT_SCRIPT => $firstBootScriptOption,
            '--' . InstanceCreateCommand::OPTION_SECRETS_JSON => $secretsJsonOption,
        ]);

        $instanceRepository = \Mockery::mock(InstanceRepository::class);
        $instanceRepository
            ->shouldReceive('findCurrent')
            ->with($serviceId, $imageId)
            ->andReturnNull()
        ;

        $instance = InstanceFactory::create([
            'id' => 123,
        ]);

        $instanceRepository
            ->shouldReceive('create')
            ->with($serviceId, $imageId, $expectedFirstBootScript)
            ->andReturn($instance)
        ;

        ObjectReflector::setProperty(
            $this->command,
            InstanceCreateCommand::class,
            'instanceRepository',
            $instanceRepository
        );

        $serviceConfiguration = \Mockery::mock(ServiceConfiguration::class);
        $serviceConfiguration
            ->shouldReceive('getEnvironmentVariables')
            ->with($serviceId)
            ->andReturn($environmentVariableList)
        ;

        ObjectReflector::setProperty(
            $this->command,
            InstanceCreateCommand::class,
            'serviceConfiguration',
            $serviceConfiguration
        );

        $commandReturnCode = $this->command->run($input, new NullOutput());

        self::assertSame(Command::SUCCESS, $commandReturnCode);
    }

    /**
     * @return array<mixed>
     */
    public function passesFirstBootScriptDataProvider(): array
    {
        return [
            'first boot script option only' => [
                'firstBootScriptOption' => './first-boot.sh',
                'secretsJsonOption' => '',
                'environmentVariableList' => new EnvironmentVariableList([]),
                'expectedFirstBootScript' => './first-boot.sh',
            ],
            'env var options only, no secrets' => [
                'firstBootScriptOption' => '',
                'secretsJsonOption' => '',
                'environmentVariableList' => new EnvironmentVariableList([
                    'key1=value1',
                    'key2=one "two" three',
                    'key3=value3',
                ]),
                'expectedFirstBootScript' => 'export key1="value1"' . "\n" .
                    'export key2="one \"two\" three"' . "\n" .
                    'export key3="value3"',
            ],
            'env var options only, has secrets' => [
                'firstBootScriptOption' => '',
                'secretsJsonOption' => '{"SERVICE_ID_SECRET_001":"secret 001 value"}',
                'environmentVariableList' => new EnvironmentVariableList([
                    'key1={{ secrets.SERVICE_ID_SECRET_001 }}',
                    'key2=one "two" three',
                    'key3=value3',
                ]),
                'expectedFirstBootScript' => 'export key1="secret 001 value"' . "\n" .
                    'export key2="one \"two\" three"' . "\n" .
                    'export key3="value3"',
            ],
            'first boot script option and env var options, no secrets' => [
                'firstBootScriptOption' => './first-boot.sh',
                'secretsJsonOption' => '',
                'environmentVariableList' => new EnvironmentVariableList([
                    'key1=value1',
                    'key2=one "two" three',
                    'key3=value3',
                ]),
                'expectedFirstBootScript' => 'export key1="value1"' . "\n" .
                    'export key2="one \"two\" three"' . "\n" .
                    'export key3="value3"' . "\n" .
                    './first-boot.sh',
            ],
        ];
    }

    /**
     * @dataProvider throwsMissingSecretExceptionDataProvider
     */
    public function testThrowsMissingSecretException(
        string $serviceId,
        string $secretsJsonOption,
        EnvironmentVariableList $environmentVariableList,
        string $expectedExceptionMessage
    ): void {
        $imageId = 'image-id';

        $input = new ArrayInput([
            '--' . Option::OPTION_SERVICE_ID => $serviceId,
            '--' . Option::OPTION_IMAGE_ID => $imageId,
            '--' . InstanceCreateCommand::OPTION_SECRETS_JSON => $secretsJsonOption,
        ]);

        $instanceRepository = \Mockery::mock(InstanceRepository::class);
        $instanceRepository
            ->shouldReceive('findCurrent')
            ->with($serviceId, $imageId)
            ->andReturnNull()
        ;

        ObjectReflector::setProperty(
            $this->command,
            InstanceCreateCommand::class,
            'instanceRepository',
            $instanceRepository
        );

        $serviceConfiguration = \Mockery::mock(ServiceConfiguration::class);
        $serviceConfiguration
            ->shouldReceive('getEnvironmentVariables')
            ->with($serviceId)
            ->andReturn($environmentVariableList)
        ;

        ObjectReflector::setProperty(
            $this->command,
            InstanceCreateCommand::class,
            'serviceConfiguration',
            $serviceConfiguration
        );

        self::expectException(MissingSecretException::class);
        self::expectExceptionMessage($expectedExceptionMessage);

        $this->command->run($input, new NullOutput());
    }

    /**
     * @return array<mixed>
     */
    public function throwsMissingSecretExceptionDataProvider(): array
    {
        return [
            'no secrets, env var references missing secret' => [
                'serviceIdOption' => 'service_id',
                'secretsJsonOption' => '',
                'environmentVariableList' => new EnvironmentVariableList([
                    'key1={{ secrets.SERVICE_ID_SECRET_001 }}',
                ]),
                'expectedExceptionMessage' => 'Secret "SERVICE_ID_SECRET_001" not found',
            ],
            'has secrets, env var references missing secret not having service id as prefix' => [
                'serviceIdOption' => 'service_id',
                'secretsJsonOption' => '',
                'environmentVariableList' => new EnvironmentVariableList([
                    'key1={{ secrets.DIFFERENT_SERVICE_ID_SECRET_001 }}',
                ]),
                'expectedExceptionMessage' => 'Secret "DIFFERENT_SERVICE_ID_SECRET_001" not found',
            ],
        ];
    }

    private function setHttpResponse(ResponseInterface $response): void
    {
        $container = self::getContainer();
        $mockHandler = $container->get(MockHandler::class);
        if ($mockHandler instanceof MockHandler) {
            $mockHandler->append($response);
        }
    }
}
