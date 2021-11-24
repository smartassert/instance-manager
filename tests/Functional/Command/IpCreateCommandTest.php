<?php

namespace App\Tests\Functional\Command;

use App\Command\IpCreateCommand;
use App\Command\Option;
use App\Tests\Services\DropletDataFactory;
use App\Tests\Services\HttpResponseFactory;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

class IpCreateCommandTest extends KernelTestCase
{
    private const COLLECTION_TAG = 'service-id';

    private IpCreateCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(IpCreateCommand::class);
        \assert($command instanceof IpCreateCommand);
        $this->command = $command;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
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
                    '--' . Option::OPTION_IMAGE_ID => '123456',
                ],
                'expectedReturnCode' => IpCreateCommand::EXIT_CODE_EMPTY_COLLECTION_TAG,
            ],
            'empty tag' => [
                'input' => [
                    '--' . Option::OPTION_COLLECTION_TAG => 'service-id',
                ],
                'expectedReturnCode' => IpCreateCommand::EXIT_CODE_EMPTY_TAG,
            ],
        ];
    }

    /**
     * @dataProvider runSuccessDataProvider
     *
     * @param array<int, array<mixed>> $httpResponseDataCollection
     */
    public function testRunSuccess(
        array $httpResponseDataCollection,
        int $expectedExitCode,
        string $expectedOutput,
    ): void {
        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->mockHandler->append(
                $this->httpResponseFactory->createFromArray($httpResponseData)
            );
        }

        $output = new BufferedOutput();
        $input = new ArrayInput([
            '--' . Option::OPTION_COLLECTION_TAG => self::COLLECTION_TAG,
            '--' . Option::OPTION_IMAGE_ID => '123456',
        ]);

        $exitCode = $this->command->run($input, $output);

        self::assertSame($expectedExitCode, $exitCode);
        self::assertJsonStringEqualsJsonString($expectedOutput, $output->fetch());
    }

    /**
     * @return array<mixed>
     */
    public function runSuccessDataProvider(): array
    {
        return [
            'no current instance' => [
                'httpResponseDataCollection' => [
                    'droplets response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [],
                        ])
                    ],
                ],
                'expectedExitCode' => IpCreateCommand::EXIT_CODE_NO_CURRENT_INSTANCE,
                'expectedOutput' => (string) json_encode([
                    'status' => 'error',
                    'error-code' => 'no-instance',
                ]),
            ],
            'ip already exists' => [
                'httpResponseDataCollection' => [
                    'droplets response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                [
                                    'id' => 123,
                                ],
                            ],
                        ]),
                    ],
                    'ip find response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'floating_ips' => [
                                [
                                    'ip' => '127.0.0.200',
                                    'droplet' => [
                                        'id' => 123,
                                        'tags' => [
                                            self::COLLECTION_TAG,
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ],
                ],
                'expectedExitCode' => IpCreateCommand::EXIT_CODE_HAS_IP,
                'expectedOutput' => (string) json_encode([
                    'status' => 'error',
                    'error-code' => 'has-ip',
                    'ip' => '127.0.0.200',
                ]),
            ],
            'created' => [
                'httpResponseDataCollection' => [
                    'droplets response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                [
                                    'id' => 123,
                                ],
                            ],
                        ]),
                    ],
                    'ip find response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'floating_ips' => [],
                        ]),
                    ],
                    'ip create response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'floating_ip' => [
                                'ip' => '127.0.0.100',
                            ],
                        ]),
                    ],
                    'droplet find response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplet' => DropletDataFactory::createWithIps(123, ['127.0.0.100']),
                        ]),
                    ],
                ],
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    'status' => 'success',
                    'ip' => '127.0.0.100',
                    'target-instance' => 123,
                ]),
            ],
        ];
    }
}
