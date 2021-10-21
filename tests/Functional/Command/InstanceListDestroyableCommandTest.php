<?php

namespace App\Tests\Functional\Command;

use App\Command\InstanceListDestroyableCommand;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
     * @dataProvider executeThrowsExceptionDataProvider
     *
     * @param array<mixed>             $responseData
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    public function testExecuteThrowsException(
        array $responseData,
        string $expectedExceptionClass,
        string $expectedExceptionMessage,
        int $expectedExceptionCode
    ): void {
        $this->mockHandler->append($this->httpResponseFactory->createFromArray($responseData));

        self::expectException($expectedExceptionClass);
        self::expectExceptionMessage($expectedExceptionMessage);
        self::expectExceptionCode($expectedExceptionCode);

        $this->command->run(new ArrayInput([]), new BufferedOutput());
    }

    /**
     * @return array<mixed>
     */
    public function executeThrowsExceptionDataProvider(): array
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
     * @dataProvider executeDataProvider
     *
     * @param array<mixed> $input
     * @param array<mixed> $httpResponseDataCollection
     */
    public function testExecuteSuccess(
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
    public function executeDataProvider(): array
    {
        $excludedIp = '127.0.0.1';

        $dropletData = [
            'instance-1' => [
                'id' => 1,
                'networks' => [
                    'v4' => [
                        [
                            'ip_address' => $excludedIp,
                        ],
                    ],
                ],
            ],
            'instance-2' => [
                'id' => 2,
                'networks' => [
                    'v4' => [
                        [
                            'ip_address' => '127.0.0.2',
                        ],
                    ],
                ],
            ],
            'instance-3' => [
                'id' => 3,
                'networks' => [
                    'v4' => [
                        [
                            'ip_address' => '127.0.0.3',
                        ],
                    ],
                ],
            ],
            'instance-4' => [
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
            'instance-1' => [
                'version' => '0.1',
                'idle' => true,
            ],
            'instance-2' => [
                'version' => '0.2',
                'idle' => false,
            ],
            'instance-3' => [
                'version' => '0.3',
                'idle' => true,
            ],
            'instance-4' => [
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
                HttpResponseFactory::KEY_BODY => json_encode($stateResponseData['instance-1']),
            ],
            '2-state' => [
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json',
                ],
                HttpResponseFactory::KEY_BODY => json_encode($stateResponseData['instance-2']),
            ],
            '3-state' => [
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json',
                ],
                HttpResponseFactory::KEY_BODY => json_encode($stateResponseData['instance-3']),
            ],
            '4-state' => [
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json',
                ],
                HttpResponseFactory::KEY_BODY => json_encode($stateResponseData['instance-4']),
            ],
        ];

        $expectedOutputData = [
            'instance-1' => [
                'id' => 1,
                'state' => array_merge(
                    [
                        'ips' => [
                            $excludedIp,
                        ],
                    ],
                    $stateResponseData['instance-1']
                ),
            ],
            'instance-2' => [
                'id' => 2,
                'state' => array_merge(
                    [
                        'ips' => [
                            '127.0.0.2',
                        ],
                    ],
                    $stateResponseData['instance-2']
                ),
            ],
            'instance-3' => [
                'id' => 3,
                'state' => array_merge(
                    [
                        'ips' => [
                            '127.0.0.3',
                        ],
                    ],
                    $stateResponseData['instance-3']
                ),
            ],
            'instance-4' => [
                'id' => 4,
                'state' => array_merge(
                    [
                        'ips' => [
                            '127.0.0.4',
                        ],
                    ],
                    $stateResponseData['instance-4']
                ),
            ],
        ];

        $input = [
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
                                $dropletData['instance-3'],
                            ],
                        ]),
                    ],
                    'state' => $collectionHttpResponses['3-state'],
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-3'],
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
                                $dropletData['instance-1'],
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
                                $dropletData['instance-2'],
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
                    $expectedOutputData['instance-3'],
                ]),
            ],
        ];
    }
}