<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceCreateCommand;
use App\Command\Option;
use App\Model\EnvironmentVariable;
use App\Services\BootScriptFactory;
use App\Services\InstanceRepository;
use App\Services\ServiceConfiguration;
use App\Services\ServiceEnvironmentVariableRepository;
use App\Tests\Mock\MockServiceConfiguration;
use App\Tests\Services\HttpResponseDataFactory;
use App\Tests\Services\HttpResponseFactory;
use App\Tests\Services\InstanceFactory;
use DigitalOceanV2\Exception\RuntimeException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    use MissingServiceIdTestTrait;
    use MissingImageIdTestTrait;

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

        $this->setCommandServiceConfiguration(
            (new MockServiceConfiguration())
                ->withExistsCall(self::SERVICE_ID, true)
                ->withGetImageIdCall(self::SERVICE_ID, self::IMAGE_ID)
                ->getMock()
        );

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

        $this->setCommandServiceConfiguration(
            (new MockServiceConfiguration())
                ->withExistsCall(self::SERVICE_ID, true)
                ->withGetImageIdCall(self::SERVICE_ID, self::IMAGE_ID)
                ->getMock()
        );

        $this->mockEnvironmentVariableRepository(new ArrayCollection());

        $invalidFirstBootScript = '#invalid first boot script';

        $bootScriptFactory = \Mockery::mock(BootScriptFactory::class);
        $bootScriptFactory
            ->shouldReceive('create')
            ->withArgs(function (Collection $collection, string $script) use ($firstBootScript) {
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

        $this->setCommandServiceConfiguration(
            (new MockServiceConfiguration())
                ->withExistsCall(self::SERVICE_ID, true)
                ->withGetImageIdCall(self::SERVICE_ID, self::IMAGE_ID)
                ->getMock()
        );
        $this->mockEnvironmentVariableRepository(new ArrayCollection());

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
                            ]
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
     *
     * @param Collection<int, EnvironmentVariable> $environmentVariableList
     */
    public function testPassesFirstBootScript(
        string $firstBootScriptOption,
        string $secretsJsonOption,
        Collection $environmentVariableList,
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

        $this->setCommandServiceConfiguration(
            (new MockServiceConfiguration())
                ->withExistsCall(self::SERVICE_ID, true)
                ->withGetImageIdCall(self::SERVICE_ID, self::IMAGE_ID)
                ->getMock()
        );
        $this->mockEnvironmentVariableRepository($environmentVariableList, $secretsJsonOption);

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
                'environmentVariableList' => new ArrayCollection(),
                'expectedFirstBootScript' => '#!/usr/bin/env bash' . "\n" .
                    './first-boot.sh',
            ],
            'env var options only, no secrets' => [
                'firstBootScriptOption' => '',
                'secretsJsonOption' => '',
                'environmentVariableList' => new ArrayCollection([
                    new EnvironmentVariable('key1', 'value1'),
                ]),
                'expectedFirstBootScript' => '#!/usr/bin/env bash' . "\n" .
                    'export key1="value1"',
            ],
            'env var options only, has secrets' => [
                'firstBootScriptOption' => '',
                'secretsJsonOption' => '{"SERVICE_ID_SECRET_001":"secret 001 value"}',
                'environmentVariableList' => new ArrayCollection([
                    new EnvironmentVariable('key1', 'secret 001 value'),
                ]),
                'expectedFirstBootScript' => '#!/usr/bin/env bash' . "\n" .
                    'export key1="secret 001 value"',
            ],
            'first boot script option and env var options, no secrets' => [
                'firstBootScriptOption' => './first-boot.sh',
                'secretsJsonOption' => '',
                'environmentVariableList' => new ArrayCollection([
                    new EnvironmentVariable('key1', 'value1'),
                ]),
                'expectedFirstBootScript' => '#!/usr/bin/env bash' . "\n" .
                    'export key1="value1"' . "\n" .
                    './first-boot.sh',
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

    /**
     * @param null|Collection<int, EnvironmentVariable> $environmentVariables
     */
    private function mockEnvironmentVariableRepository(
        ?Collection $environmentVariables = null,
        string $secretsJson = '',
    ): void {
        $environmentVariableRepository = \Mockery::mock(ServiceEnvironmentVariableRepository::class);

        if ($environmentVariables instanceof Collection) {
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
