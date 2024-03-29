<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceIsActiveCommand;
use App\Command\Option;
use App\Exception\RequiredOptionMissingException;
use App\Model\Instance;
use App\Tests\Services\HttpResponseDataFactory;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

class InstanceIsActiveCommandTest extends KernelTestCase
{
    private InstanceIsActiveCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(InstanceIsActiveCommand::class);
        \assert($command instanceof InstanceIsActiveCommand);
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
        foreach ($httpResponseDataCollection as $fixture) {
            if (is_array($fixture)) {
                $fixture = $this->httpResponseFactory->createFromArray($fixture);
            }

            $this->mockHandler->append($fixture);
        }

        $output = new BufferedOutput();

        $commandReturnCode = $this->command->run(new ArrayInput($input), $output);

        self::assertSame($expectedReturnCode, $commandReturnCode);
        self::assertEquals($expectedOutput, $output->fetch());
    }

    /**
     * @return array<mixed>
     */
    public function runDataProvider(): array
    {
        return [
            'not active, retry limit=1, state: new' => [
                'input' => [
                    '--id' => '123',
                    '--retry-limit' => 1,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 123,
                            'status' => Instance::DROPLET_STATUS_NEW,
                        ],
                    ]),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'new',
            ],
            'not active, retry limit=1, state: off' => [
                'input' => [
                    '--id' => '123',
                    '--retry-limit' => 1,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 123,
                            'status' => Instance::DROPLET_STATUS_OFF,
                        ],
                    ]),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'off',
            ],
            'not active, retry limit=1, state: archive' => [
                'input' => [
                    '--id' => '123',
                    '--retry-limit' => 1,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 123,
                            'status' => Instance::DROPLET_STATUS_ARCHIVE,
                        ],
                    ]),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'archive',
            ],
            'not active, retry limit=1, state: unknown' => [
                'input' => [
                    '--id' => '123',
                    '--retry-limit' => 1,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 123,
                            'status' => 'unknown-state',
                        ],
                    ]),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'unknown',
            ],
            'active, retry limit=1, state: active' => [
                'input' => [
                    '--id' => '123',
                    '--retry-limit' => 1,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 123,
                            'status' => Instance::DROPLET_STATUS_ACTIVE,
                        ],
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => 'active',
            ],
            'not active, not active, retry limit=2' => [
                'input' => [
                    '--id' => '123',
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 123,
                            'status' => Instance::DROPLET_STATUS_NEW,
                        ],
                    ]),
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 123,
                            'status' => Instance::DROPLET_STATUS_NEW,
                        ],
                    ]),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'new' . "\n" . 'new',
            ],
            'not active, 404, retry limit=2' => [
                'input' => [
                    '--id' => '123',
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 123,
                            'status' => Instance::DROPLET_STATUS_NEW,
                        ],
                    ]),
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 404,
                    ],
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'new' . "\n" . 'unknown',
            ],
            'not active, active, retry limit=2' => [
                'input' => [
                    '--id' => '123',
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 123,
                            'status' => Instance::DROPLET_STATUS_NEW,
                        ],
                    ]),
                    HttpResponseDataFactory::createJsonResponseData([
                        'droplet' => [
                            'id' => 123,
                            'status' => Instance::DROPLET_STATUS_ACTIVE,
                        ],
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => 'new' . "\n" . 'active',
            ],
        ];
    }

    /**
     * @dataProvider runWithoutInstanceIdThrowsExceptionDataProvider
     *
     * @param array<mixed> $input
     */
    public function testRunWithoutInstanceIdThrowsException(string $serviceId, array $input): void
    {
        $this->expectExceptionObject(new RequiredOptionMissingException(Option::OPTION_ID));

        $this->command->run(new ArrayInput($input), new NullOutput());
    }

    /**
     * @return array<mixed>
     */
    public function runWithoutInstanceIdThrowsExceptionDataProvider(): array
    {
        $serviceId = md5((string) rand());

        return [
            'missing' => [
                'serviceId' => $serviceId,
                'input' => [],
            ],
            'not numeric' => [
                'serviceId' => $serviceId,
                'input' => [
                    '--' . Option::OPTION_ID => 'not-numeric',
                ],
            ],
        ];
    }
}
