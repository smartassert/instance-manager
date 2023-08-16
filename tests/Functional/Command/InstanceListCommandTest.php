<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceListCommand;
use App\Command\Option;
use App\Tests\Services\HttpResponseDataFactory;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class InstanceListCommandTest extends KernelTestCase
{
    use MockeryPHPUnitIntegration;
    use MissingServiceIdTestTrait;

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

    public function testRunInvalidApiToken(): void
    {
        $serviceId = 'service_id';

        $this->mockHandler->append(new Response(401));

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unauthorized');
        self::expectExceptionCode(401);

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => $serviceId,
            ]),
            new BufferedOutput()
        );
    }

    /**
     * @dataProvider runSuccessDataProvider
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
        $this->doTestRun(
            $input,
            $httpResponseDataCollection,
            function (int $returnCode, string $output) use ($expectedReturnCode, $expectedOutput) {
                self::assertSame($expectedReturnCode, $returnCode);
                self::assertJsonStringEqualsJsonString($expectedOutput, $output);
            }
        );
    }

    /**
     * @return array<mixed>
     */
    public function runSuccessDataProvider(): array
    {
        $matchingIp = '127.0.0.1';

        $dropletData = [
            'instance-1' => [
                'id' => 1,
                'created_at' => '2020-01-02T01:01:01.000Z',
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
                'created_at' => '2020-01-02T02:02:02.000Z',
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
                'created_at' => '2020-01-02T03:03:03.000Z',
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
                'created_at' => '2020-01-02T04:04:04.000Z',
                'networks' => [
                    'v4' => [
                        [
                            'ip_address' => '127.0.0.4',
                        ],
                    ],
                ],
            ],
        ];

        $collectionHttpResponses = [
            'droplets' => HttpResponseDataFactory::createJsonResponseData([
                'droplets' => array_values($dropletData),
            ]),
        ];

        $expectedOutputData = [
            'instance-1' => [
                'id' => 1,
                'state' => array_merge(
                    [
                        'ips' => [
                            $matchingIp,
                        ],
                        'created_at' => '2020-01-02T01:01:01.000Z',
                    ],
                ),
            ],
            'instance-2' => [
                'id' => 2,
                'state' => array_merge(
                    [
                        'ips' => [
                            '127.0.0.2',
                        ],
                        'created_at' => '2020-01-02T02:02:02.000Z',
                    ],
                ),
            ],
            'instance-3' => [
                'id' => 3,
                'state' => array_merge(
                    [
                        'ips' => [
                            '127.0.0.3',
                        ],
                        'created_at' => '2020-01-02T03:03:03.000Z',
                    ],
                ),
            ],
            'instance-4' => [
                'id' => 4,
                'state' => array_merge(
                    [
                        'ips' => [
                            '127.0.0.4',
                        ],
                        'created_at' => '2020-01-02T04:04:04.000Z',
                    ],
                ),
            ],
        ];

        return [
            'no instances' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                ],
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
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                ],
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
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-1'],
                ]),
            ],
            'many instances' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                ],
                'httpResponseDataCollection' => $collectionHttpResponses,
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-1'],
                    $expectedOutputData['instance-2'],
                    $expectedOutputData['instance-3'],
                    $expectedOutputData['instance-4'],
                ]),
            ],
        ];
    }

    /**
     * @param array<mixed>                $input
     * @param array<int, array<mixed>>    $httpResponseDataCollection
     * @param callable(int, string): void $assertions
     */
    private function doTestRun(
        array $input,
        array $httpResponseDataCollection,
        callable $assertions
    ): void {
        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->mockHandler->append($this->httpResponseFactory->createFromArray($httpResponseData));
        }

        $output = new BufferedOutput();

        $commandReturnCode = $this->command->run(new ArrayInput($input), $output);

        $assertions($commandReturnCode, $output->fetch());
    }
}
