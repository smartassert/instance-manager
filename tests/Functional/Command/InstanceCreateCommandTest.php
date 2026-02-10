<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceCreateCommand;
use App\Command\Option;
use App\Enum\Filename;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableCollection;
use App\Services\BootScriptFactory;
use App\Services\ImageIdLoaderInterface;
use App\Services\InstanceRepository;
use App\Services\ServiceEnvironmentVariableRepository;
use App\Tests\Services\HttpResponseDataFactory;
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

/**
 * @phpstan-import-type HttpResponseData from HttpResponseFactory
 */
class InstanceCreateCommandTest extends KernelTestCase
{
    use MissingServiceIdTestTrait;

    private const SERVICE_ID = 'service_id';
    private const IMAGE_ID = '12345';

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
     * @param array<int, HttpResponseData> $responseData
     * @param class-string<\Throwable>     $expectedExceptionClass
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

        $imageIdLoader = \Mockery::mock(ImageIdLoaderInterface::class);
        $imageIdLoader
            ->shouldReceive('load')
            ->with('service_id')
            ->andReturn(self::IMAGE_ID)
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'imageIdLoader', $imageIdLoader);

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => 'service_id',
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

    public function testRunFirstBootScriptInvalid(): void
    {
        $firstBootScript = './executable.sh';

        $input = new ArrayInput([
            '--' . Option::OPTION_SERVICE_ID => self::SERVICE_ID,
            '--' . InstanceCreateCommand::OPTION_FIRST_BOOT_SCRIPT => $firstBootScript,
        ]);

        $output = new BufferedOutput();

        $this->setHttpResponse(
            $this->httpResponseFactory->createFromArray(HttpResponseDataFactory::createJsonResponseData([
                'droplets' => [],
            ]))
        );

        $imageIdLoader = \Mockery::mock(ImageIdLoaderInterface::class);
        $imageIdLoader
            ->shouldReceive('load')
            ->with('service_id')
            ->andReturn(self::IMAGE_ID)
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'imageIdLoader', $imageIdLoader);

        $this->mockEnvironmentVariableRepository(new EnvironmentVariableCollection());

        $invalidFirstBootScript = '#invalid first boot script';

        $bootScriptFactory = \Mockery::mock(BootScriptFactory::class);
        $bootScriptFactory
            ->shouldReceive('create')
            ->withArgs(function (EnvironmentVariableCollection $collection, string $script) use ($firstBootScript) {
                self::assertSame($firstBootScript, $script);

                return true;
            })
            ->andReturn($invalidFirstBootScript)
        ;

        $bootScriptFactory
            ->shouldReceive('validate')
            ->with($invalidFirstBootScript)
            ->andReturn(false)
        ;

        ObjectReflector::setProperty(
            $this->command,
            $this->command::class,
            'bootScriptFactory',
            $bootScriptFactory
        );

        $commandReturnCode = $this->command->run($input, $output);

        self::assertSame(InstanceCreateCommand::EXIT_CODE_FIRST_BOOT_SCRIPT_INVALID, $commandReturnCode);
    }

    /**
     * @dataProvider runDataProvider
     *
     * @param array<mixed>                 $input
     * @param array<int, HttpResponseData> $httpResponseDataCollection
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

        $imageIdLoader = \Mockery::mock(ImageIdLoaderInterface::class);
        $imageIdLoader
            ->shouldReceive('load')
            ->with('service_id')
            ->andReturn(self::IMAGE_ID)
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'imageIdLoader', $imageIdLoader);

        $this->mockEnvironmentVariableRepository(new EnvironmentVariableCollection());

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
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplets' => [
                            [
                                'id' => 123,
                            ],
                        ],
                    ]),
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
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplets' => [],
                    ]),
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 789,
                        ],
                    ]),
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
        EnvironmentVariableCollection $environmentVariables,
        string $expectedFirstBootScript,
    ): void {
        $input = new ArrayInput([
            '--' . Option::OPTION_SERVICE_ID => self::SERVICE_ID,
            '--' . InstanceCreateCommand::OPTION_FIRST_BOOT_SCRIPT => $firstBootScriptOption,
            '--' . InstanceCreateCommand::OPTION_SECRETS_JSON => $secretsJsonOption,
        ]);

        $instanceRepository = \Mockery::mock(InstanceRepository::class);
        $instanceRepository
            ->shouldReceive('findCurrent')
            ->with(self::SERVICE_ID, self::IMAGE_ID)
            ->andReturnNull()
        ;

        $instance = InstanceFactory::create([
            'id' => 123,
        ]);

        $instanceRepository
            ->shouldReceive('create')
            ->with(self::SERVICE_ID, self::IMAGE_ID, $expectedFirstBootScript)
            ->andReturn($instance)
        ;

        ObjectReflector::setProperty(
            $this->command,
            InstanceCreateCommand::class,
            'instanceRepository',
            $instanceRepository
        );

        $imageIdLoader = \Mockery::mock(ImageIdLoaderInterface::class);
        $imageIdLoader
            ->shouldReceive('load')
            ->with('service_id')
            ->andReturn(self::IMAGE_ID)
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'imageIdLoader', $imageIdLoader);

        $this->mockEnvironmentVariableRepository($environmentVariables, $secretsJsonOption);

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
                'environmentVariables' => new EnvironmentVariableCollection(),
                'expectedFirstBootScript' => '#!/usr/bin/env bash' . "\n"
                    . './first-boot.sh',
            ],
            'env var options only, no secrets' => [
                'firstBootScriptOption' => '',
                'secretsJsonOption' => '',
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'value1'),
                ]),
                'expectedFirstBootScript' => '#!/usr/bin/env bash' . "\n"
                    . 'export key1="value1"',
            ],
            'env var options only, has secrets' => [
                'firstBootScriptOption' => '',
                'secretsJsonOption' => '{"SERVICE_ID_SECRET_001":"secret 001 value"}',
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'secret 001 value'),
                ]),
                'expectedFirstBootScript' => '#!/usr/bin/env bash' . "\n"
                    . 'export key1="secret 001 value"',
            ],
            'first boot script option and env var options, no secrets' => [
                'firstBootScriptOption' => './first-boot.sh',
                'secretsJsonOption' => '',
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'value1'),
                ]),
                'expectedFirstBootScript' => '#!/usr/bin/env bash' . "\n"
                    . 'export key1="value1"' . "\n"
                    . './first-boot.sh',
            ],
        ];
    }

    public function testRunWithoutServiceConfigurationFileThrowsException(): void
    {
        $serviceId = 'service_id';

        $this->expectExceptionObject(
            new ServiceConfigurationMissingException($serviceId, Filename::IMAGE->value)
        );

        $this->command->run(new ArrayInput(['--' . Option::OPTION_SERVICE_ID => $serviceId]), new NullOutput());
    }

    public function testRunWithoutImageIdThrowsException(): void
    {
        $serviceId = 'service_id';

        $exception = new ConfigurationFileValueMissingException(
            Filename::IMAGE->value,
            'image_id',
            'service_id'
        );

        $imageIdLoader = \Mockery::mock(ImageIdLoaderInterface::class);
        $imageIdLoader
            ->shouldReceive('load')
            ->with('service_id')
            ->andThrow($exception)
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'imageIdLoader', $imageIdLoader);

        $this->expectExceptionObject($exception);
        $this->command->run(new ArrayInput(['--' . Option::OPTION_SERVICE_ID => $serviceId]), new NullOutput());
    }

    private function setHttpResponse(ResponseInterface $response): void
    {
        $container = self::getContainer();
        $mockHandler = $container->get(MockHandler::class);
        if ($mockHandler instanceof MockHandler) {
            $mockHandler->append($response);
        }
    }

    private function mockEnvironmentVariableRepository(
        ?EnvironmentVariableCollection $environmentVariables = null,
        string $secretsJson = '',
    ): void {
        $environmentVariableRepository = \Mockery::mock(ServiceEnvironmentVariableRepository::class);

        if ($environmentVariables instanceof EnvironmentVariableCollection) {
            $environmentVariableRepository
                ->shouldReceive('getCollection')
                ->with(self::SERVICE_ID, $secretsJson)
                ->andReturn($environmentVariables)
            ;
        }

        ObjectReflector::setProperty(
            $this->command,
            $this->command::class,
            'environmentVariableRepository',
            $environmentVariableRepository
        );
    }
}
