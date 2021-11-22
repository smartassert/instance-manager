<?php

namespace App\Tests\Functional\Command;

use App\Command\InstanceCreateCommand;
use App\Model\EnvironmentVariableList;
use App\Services\BootScriptFactory;
use App\Services\CommandConfigurator;
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
                '--' . CommandConfigurator::OPTION_COLLECTION_TAG => 'service-id',
                '--' . CommandConfigurator::OPTION_IMAGE_ID => '123456',
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
            'empty collection tag' => [
                'input' => [
                    '--' . CommandConfigurator::OPTION_IMAGE_ID => '123456',
                ],
                'expectedReturnCode' => InstanceCreateCommand::EXIT_CODE_EMPTY_COLLECTION_TAG,
            ],
            'empty tag' => [
                'input' => [
                    '--' . CommandConfigurator::OPTION_COLLECTION_TAG => 'service-id',
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
                    '--' . CommandConfigurator::OPTION_COLLECTION_TAG => 'service-id',
                    '--' . CommandConfigurator::OPTION_IMAGE_ID => '123456',
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
                    '--' . CommandConfigurator::OPTION_COLLECTION_TAG => 'service-id',
                    '--' . CommandConfigurator::OPTION_IMAGE_ID => '123456',
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
        EnvironmentVariableList $serviceEnvironmentVariables,
        string $expectedFirstBootScript,
    ): void {
        $collectionTag = 'collection-tag';
        $imageId = 'image-id';

        $input = new ArrayInput([
            '--' . CommandConfigurator::OPTION_COLLECTION_TAG => $collectionTag,
            '--' . CommandConfigurator::OPTION_IMAGE_ID => $imageId,
        ]);

        $serviceConfiguration = \Mockery::mock(ServiceConfiguration::class);
        $serviceConfiguration
            ->shouldReceive('getEnvironmentVariables')
            //->with('foo')
            ->andReturn($serviceEnvironmentVariables)
        ;

        $bootScriptFactory = self::getContainer()->get(BootScriptFactory::class);
        \assert($bootScriptFactory instanceof BootScriptFactory);
        ObjectReflector::setProperty(
            $bootScriptFactory,
            $bootScriptFactory::class,
            'serviceConfiguration',
            $serviceConfiguration
        );

        $instanceServiceScriptCaller = self::getContainer()->getParameter('instance_service_script_caller');
        \assert(is_string($instanceServiceScriptCaller));

        $expectedFirstBootScript = str_replace(
            '{{ instance_service_script_caller }}',
            $instanceServiceScriptCaller,
            $expectedFirstBootScript
        );

        $instanceRepository = \Mockery::mock(InstanceRepository::class);
        $instanceRepository
            ->shouldReceive('findCurrent')
            ->with($collectionTag, $imageId)
            ->andReturnNull()
        ;

        $instance = InstanceFactory::create([
            'id' => 123,
        ]);

        $instanceRepository
            ->shouldReceive('create')
            ->with($collectionTag, $imageId, $expectedFirstBootScript)
            ->andReturn($instance)
        ;

        ObjectReflector::setProperty(
            $this->command,
            InstanceCreateCommand::class,
            'instanceRepository',
            $instanceRepository
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
                'serviceEnvironmentVariables' => new EnvironmentVariableList([]),
                'expectedFirstBootScript' => '{{ instance_service_script_caller }}',
            ],
            'with service environment variables' => [
                'serviceEnvironmentVariables' => new EnvironmentVariableList([
                    'key1=value1',
                    'key2=one "two" three',
                    'key3=value3',
                ]),
                'expectedFirstBootScript' => 'export key1="value1"' . "\n" .
                    'export key2="one \"two\" three"' . "\n" .
                    'export key3="value3"' . "\n" .
                    '{{ instance_service_script_caller }}',
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
