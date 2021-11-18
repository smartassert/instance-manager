<?php

namespace App\Tests\Functional\Command;

use App\Command\AbstractInstanceListCommand;
use App\Command\InstanceListDestroyableCommand;
use App\Services\CommandConfigurator;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

class InstanceListDestroyableCommandTest extends KernelTestCase
{
    private InstanceListDestroyableCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(InstanceListDestroyableCommand::class);
        \assert($command instanceof InstanceListDestroyableCommand);
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
     * @param array<mixed>             $responseData
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    public function testRunThrowsException(
        array $responseData,
        string $expectedExceptionClass,
        string $expectedExceptionMessage,
        int $expectedExceptionCode
    ): void {
        $this->mockHandler->append($this->httpResponseFactory->createFromArray($responseData));

        self::expectException($expectedExceptionClass);
        self::expectExceptionMessage($expectedExceptionMessage);
        self::expectExceptionCode($expectedExceptionCode);

        $this->command->run(
            new ArrayInput([
                '--' . CommandConfigurator::OPTION_COLLECTION_TAG => 'service-id',
            ]),
            new NullOutput()
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
                'input' => [],
                'expectedReturnCode' => AbstractInstanceListCommand::EXIT_CODE_EMPTY_COLLECTION_TAG,
            ],
        ];
    }

    /**
     * @dataProvider runDataProvider
     *
     * @param array<mixed> $input
     * @param array<int, array<mixed>> $httpResponseDataCollection
     */
    public function testRunSuccess(
        array $input,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->mockHandler->append($this->httpResponseFactory->createFromArray($httpResponseData));
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
        $excludedIp = '127.0.0.1';

        $dropletData = [
            'instance-with-excluded-ip' => [
                'id' => 1,
                'networks' => [
                    'v4' => [
                        [
                            'ip_address' => $excludedIp,
                        ],
                    ],
                ],
            ],
            'instance-not-idle' => [
                'id' => 2,
                'networks' => [
                    'v4' => [
                        [
                            'ip_address' => '127.0.0.2',
                        ],
                    ],
                ],
            ],
            'instance-is-idle' => [
                'id' => 3,
                'networks' => [
                    'v4' => [
                        [
                            'ip_address' => '127.0.0.3',
                        ],
                    ],
                ],
            ],
            'instance-null-idle' => [
                'id' => 4,
                'networks' => [
                    'v4' => [
                        [
                            'ip_address' => '127.0.0.4',
                        ],
                    ],
                ],
            ],
        ];

        $stateResponseData = [
            'instance-with-excluded-ip' => [
                'version' => '0.1',
                'idle' => true,
            ],
            'instance-not-idle' => [
                'version' => '0.2',
                'idle' => false,
            ],
            'instance-is-idle' => [
                'version' => '0.3',
                'idle' => true,
            ],
            'instance-null-idle' => [
                'version' => '0.4',
            ],
        ];

        $collectionHttpResponses = [
            'droplets' => [
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json; charset=utf-8',
                ],
                HttpResponseFactory::KEY_BODY => (string) json_encode([
                    'droplets' => array_values($dropletData),
                ]),
            ],
            '1-state' => [
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json',
                ],
                HttpResponseFactory::KEY_BODY => json_encode($stateResponseData['instance-with-excluded-ip']),
            ],
            '2-state' => [
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json',
                ],
                HttpResponseFactory::KEY_BODY => json_encode($stateResponseData['instance-not-idle']),
            ],
            '3-state' => [
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json',
                ],
                HttpResponseFactory::KEY_BODY => json_encode($stateResponseData['instance-is-idle']),
            ],
            '4-state' => [
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json',
                ],
                HttpResponseFactory::KEY_BODY => json_encode($stateResponseData['instance-null-idle']),
            ],
        ];

        $expectedOutputData = [
            'instance-with-excluded-ip' => [
                'id' => 1,
                'state' => array_merge(
                    [
                        'ips' => [
                            $excludedIp,
                        ],
                    ],
                    $stateResponseData['instance-with-excluded-ip']
                ),
            ],
            'instance-not-idle' => [
                'id' => 2,
                'state' => array_merge(
                    [
                        'ips' => [
                            '127.0.0.2',
                        ],
                    ],
                    $stateResponseData['instance-not-idle']
                ),
            ],
            'instance-is-idle' => [
                'id' => 3,
                'state' => array_merge(
                    [
                        'ips' => [
                            '127.0.0.3',
                        ],
                    ],
                    $stateResponseData['instance-is-idle']
                ),
            ],
            'instance-null-idle' => [
                'id' => 4,
                'state' => array_merge(
                    [
                        'ips' => [
                            '127.0.0.4',
                        ],
                    ],
                    $stateResponseData['instance-null-idle']
                ),
            ],
        ];

        $input = [
            '--' . CommandConfigurator::OPTION_COLLECTION_TAG => 'service-id',
            '--excluded-ip' => $excludedIp,
        ];

        return [
            'no instances' => [
                'input' => $input,
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [],
                        ]),
                    ],
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([]),
            ],
            'single matching instance (idle=true, does not have matching IP)' => [
                'input' => $input,
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                $dropletData['instance-is-idle'],
                            ],
                        ]),
                    ],
                    'state' => $collectionHttpResponses['3-state'],
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-is-idle'],
                ]),
            ],
            'single non-matching instance (idle=true, does have matching IP)' => [
                'input' => $input,
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                $dropletData['instance-with-excluded-ip'],
                            ],
                        ]),
                    ],
                    'state' => $collectionHttpResponses['1-state'],
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([]),
            ],
            'single non-matching instance (idle=false, does not have matching IP)' => [
                'input' => $input,
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                $dropletData['instance-not-idle'],
                            ],
                        ]),
                    ],
                    '1-state' => $collectionHttpResponses['2-state'],
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([]),
            ],
            'many instances' => [
                'input' => $input,
                'httpResponseDataCollection' => $collectionHttpResponses,
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-is-idle'],
                ]),
            ],
        ];
    }
}
