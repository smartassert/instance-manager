<?php

namespace App\Tests\Functional\Command;

use App\Command\InstanceListCommand;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class InstanceListCommandTest extends KernelTestCase
{
    private InstanceListCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(InstanceListCommand::class);
        \assert($command instanceof InstanceListCommand);
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
        $matchingIp = '127.0.0.1';

        $dropletData = [
            'instance-1' => [
                'id' => 1,
                'networks' => [
                    'v4' => [
                        [
                            'ip_address' => $matchingIp,
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
                'idle' => false,
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
                            $matchingIp,
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

        return [
            'no instances' => [
                'input' => [],
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
            'single instance' => [
                'input' => [],
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
                    '1-state' => $collectionHttpResponses['1-state'],
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-1'],
                ]),
            ],
            'many instances, no filter' => [
                'input' => [],
                'httpResponseDataCollection' => $collectionHttpResponses,
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-1'],
                    $expectedOutputData['instance-2'],
                    $expectedOutputData['instance-3'],
                    $expectedOutputData['instance-4'],
                ]),
            ],
            'many instances, filter to idle=true' => [
                'input' => [
                    '--include' => (string) json_encode([
                        [
                            'idle' => true,
                        ],
                    ]),
                ],
                'httpResponseDataCollection' => $collectionHttpResponses,
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-3'],
                ]),
            ],
            'many instances, filter to not contains IP matching IP' => [
                'input' => [
                    '--exclude' => (string) json_encode([
                        [
                            'ips' => $matchingIp,
                        ],
                    ]),
                ],
                'httpResponseDataCollection' => $collectionHttpResponses,
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-2'],
                    $expectedOutputData['instance-3'],
                    $expectedOutputData['instance-4'],
                ]),
            ],
            'many instances, filter to idle=true, not contains IP matching IP' => [
                'input' => [
                    '--include' => (string) json_encode([
                        [
                            'idle' => true,
                        ],
                    ]),
                    '--exclude' => (string) json_encode([
                        [
                            'ips' => $matchingIp,
                        ],
                    ]),
                ],
                'httpResponseDataCollection' => $collectionHttpResponses,
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-3'],
                ]),
            ],
        ];
    }
}
