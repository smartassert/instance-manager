<?php

namespace App\Tests\Functional\Command;

use App\Command\InstanceIsHealthyCommand;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray($httpResponseData)
        );

        self::expectException($expectedExceptionClass);
        self::expectExceptionMessage($expectedExceptionMessage);
        self::expectExceptionCode($expectedExceptionCode);

        $this->command->run(
            new ArrayInput([
                '--id' => '123',
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
     * @param array<mixed> $input
     * @param array<mixed> $httpResponseDataCollection
     */
    public function testRunInvalidInput(
        array $input,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->mockHandler->append(
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
    public function runInvalidInputDataProvider(): array
    {
        return [
            'id invalid, missing' => [
                'input' => [],
                'httpResponseDataCollection' => [],
                'expectedReturnCode' => InstanceIsHealthyCommand::EXIT_CODE_ID_INVALID,
                'expectedOutput' => (string) json_encode([
                    'status' => 'error',
                    'error-code' => 'id-invalid',
                ]),
            ],
            'id invalid, not numeric' => [
                'input' => [
                    '--id' => 'not-numeric',
                ],
                'httpResponseDataCollection' => [],
                'expectedReturnCode' => InstanceIsHealthyCommand::EXIT_CODE_ID_INVALID,
                'expectedOutput' => (string) json_encode([
                    'status' => 'error',
                    'error-code' => 'id-invalid',
                ]),
            ],
            'not found' => [
                'input' => [
                    '--id' => '123',
                ],
                'httpResponseDataCollection' => [
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 404,
                    ],
                ],
                'expectedReturnCode' => InstanceIsHealthyCommand::EXIT_CODE_NOT_FOUND,
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
     * @param array<mixed> $input
     * @param array<mixed> $httpResponseDataCollection
     */
    public function testRunSuccess(
        array $input,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
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
        return [
            'no health data' => [
                'input' => [
                    '--id' => '123',
                ],
                'httpResponseDataCollection' => [
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplet' => [
                                'code' => 123,
                            ],
                        ]),
                    ],
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([]),
                    ],
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => '[]',
            ],
            'not healthy, retry-limit=1' => [
                'input' => [
                    '--id' => '123',
                    '--retry-limit' => 1,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplet' => [
                                'code' => 123,
                            ],
                        ]),
                    ],
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 503,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'service1' => 'unavailable',
                            'service2' => 'available',
                            'service3' => 'available',
                        ]),
                    ],
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
                    '--id' => '123',
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplet' => [
                                'code' => 123,
                            ],
                        ]),
                    ],
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 503,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'service1' => 'unavailable',
                            'service2' => 'available',
                        ]),
                    ],
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 503,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'service1' => 'unavailable',
                            'service2' => 'available',
                        ]),
                    ],
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => json_encode(['service1' => 'unavailable', 'service2' => 'available']) . "\n" .
                    json_encode(['service1' => 'unavailable', 'service2' => 'available']),
            ],
            'not healthy, healthy, retry-limit=2' => [
                'input' => [
                    '--id' => '123',
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplet' => [
                                'code' => 123,
                            ],
                        ]),
                    ],
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 503,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'service1' => 'unavailable',
                            'service2' => 'available',
                        ]),
                    ],
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'service1' => 'available',
                            'service2' => 'available',
                        ]),
                    ],
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => json_encode(['service1' => 'unavailable', 'service2' => 'available']) . "\n" .
                    json_encode(['service1' => 'available', 'service2' => 'available']),
            ],
            'healthy' => [
                'input' => [
                    '--id' => '123',
                ],
                'httpResponseDataCollection' => [
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplet' => [
                                'code' => 123,
                            ],
                        ]),
                    ],
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'service1' => 'available',
                            'service2' => 'available',
                            'service3' => 'available',
                        ]),
                    ],
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
}
