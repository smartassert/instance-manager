<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\AbstractInstanceListCommand;
use App\Command\InstanceListCommand;
use App\Command\Option;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;
use App\Services\ServiceConfiguration;
use App\Tests\Services\HttpResponseDataFactory;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

class InstanceListCommandTest extends KernelTestCase
{
    use MockeryPHPUnitIntegration;

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
        $serviceId = 'service_id';

        $this->mockServiceConfiguration(
            $serviceId,
            new ServiceConfigurationModel(
                $serviceId,
                'https://{{ host }}/health-check',
                'https://{{ host }}/state'
            )
        );

        $this->mockHandler->append($this->httpResponseFactory->createFromArray($responseData));

        self::expectException($expectedExceptionClass);
        self::expectExceptionMessage($expectedExceptionMessage);
        self::expectExceptionCode($expectedExceptionCode);

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => $serviceId,
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
     * @dataProvider runInvalidInputDataProvider
     *
     * @param array<mixed>             $input
     * @param array<int, array<mixed>> $httpResponseDataCollection
     */
    public function testRunInvalidInput(
        array $input,
        ?ServiceConfigurationModel $serviceConfiguration,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        $this->doTestRun(
            $input,
            $serviceConfiguration,
            $httpResponseDataCollection,
            function (int $returnCode, string $output) use ($expectedReturnCode, $expectedOutput) {
                self::assertSame($expectedReturnCode, $returnCode);
                self::assertSame($expectedOutput, $output);
            }
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
        ?ServiceConfigurationModel $serviceConfiguration,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        $this->doTestRun(
            $input,
            $serviceConfiguration,
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
    public function runInvalidInputDataProvider(): array
    {
        $serviceId = 'service_id';

        return [
            'service id invalid, missing' => [
                'input' => [],
                'serviceConfiguration' => null,
                'httpResponseDataCollection' => [],
                'expectedReturnCode' => AbstractInstanceListCommand::EXIT_CODE_EMPTY_SERVICE_ID,
                'expectedOutput' => '"service-id" option empty',
            ],
            'service configuration missing' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                ],
                'serviceConfiguration' => null,
                'httpResponseDataCollection' => [],
                'expectedReturnCode' => AbstractInstanceListCommand::EXIT_CODE_SERVICE_CONFIGURATION_MISSING,
                'expectedOutput' => 'No configuration for service "service_id"',
            ],
            'service configuration state_url missing' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                ],
                'serviceConfiguration' => new ServiceConfigurationModel(
                    $serviceId,
                    'https://{{ host }}/health-check',
                    ''
                ),
                'httpResponseDataCollection' => [],
                'expectedReturnCode' => AbstractInstanceListCommand::EXIT_CODE_SERVICE_STATE_URL_MISSING,
                'expectedOutput' => 'No state_url for service "service_id"',
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function runSuccessDataProvider(): array
    {
        $serviceId = 'service_id';

        $serviceConfiguration = new ServiceConfigurationModel(
            $serviceId,
            'https://{{ host }}/health-check',
            'https://{{ host }}/state'
        );

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
            'droplets' => HttpResponseDataFactory::createJsonResponseData([
                'droplets' => array_values($dropletData),
            ]),
            '1-state' => HttpResponseDataFactory::createJsonResponseData($stateResponseData['instance-1']),
            '2-state' => HttpResponseDataFactory::createJsonResponseData($stateResponseData['instance-2']),
            '3-state' => HttpResponseDataFactory::createJsonResponseData($stateResponseData['instance-3']),
            '4-state' => HttpResponseDataFactory::createJsonResponseData($stateResponseData['instance-4']),
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
                    $stateResponseData['instance-1'],
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
                        'created_at' => '2020-01-02T03:03:03.000Z',
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
                        'created_at' => '2020-01-02T04:04:04.000Z',
                    ],
                    $stateResponseData['instance-4']
                ),
            ],
        ];

        return [
            //            'no instances' => [
            //                'input' => [
            //                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
            //                ],
            //                'serviceConfiguration' => $serviceConfiguration,
            //                'httpResponseDataCollection' => [
            //                    'droplets' => [
            //                        HttpResponseFactory::KEY_STATUS_CODE => 200,
            //                        HttpResponseFactory::KEY_HEADERS => [
            //                            'content-type' => 'application/json; charset=utf-8',
            //                        ],
            //                        HttpResponseFactory::KEY_BODY => (string) json_encode([
            //                            'droplets' => [],
            //                        ]),
            //                    ],
            //                ],
            //                'expectedReturnCode' => Command::SUCCESS,
            //                'expectedOutput' => (string) json_encode([]),
            //            ],
            //            'single instance' => [
            //                'input' => [
            //                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
            //                ],
            //                'serviceConfiguration' => $serviceConfiguration,
            //                'httpResponseDataCollection' => [
            //                    'droplets' => [
            //                        HttpResponseFactory::KEY_STATUS_CODE => 200,
            //                        HttpResponseFactory::KEY_HEADERS => [
            //                            'content-type' => 'application/json; charset=utf-8',
            //                        ],
            //                        HttpResponseFactory::KEY_BODY => (string) json_encode([
            //                            'droplets' => [
            //                                $dropletData['instance-1'],
            //                            ],
            //                        ]),
            //                    ],
            //                    '1-state' => $collectionHttpResponses['1-state'],
            //                ],
            //                'expectedReturnCode' => Command::SUCCESS,
            //                'expectedOutput' => (string) json_encode([
            //                    $expectedOutputData['instance-1'],
            //                ]),
            //            ],
            'many instances, no filter' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                ],
                'serviceConfiguration' => $serviceConfiguration,
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
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . InstanceListCommand::OPTION_INCLUDE => (string) json_encode([
                        [
                            'idle' => true,
                        ],
                    ]),
                ],
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => $collectionHttpResponses,
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-3'],
                ]),
            ],
            'many instances, filter to not contains IP matching IP' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . InstanceListCommand::OPTION_EXCLUDE => (string) json_encode([
                        [
                            'ips' => $matchingIp,
                        ],
                    ]),
                ],
                'serviceConfiguration' => $serviceConfiguration,
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
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . InstanceListCommand::OPTION_INCLUDE => (string) json_encode([
                        [
                            'idle' => true,
                        ],
                    ]),
                    '--' . InstanceListCommand::OPTION_EXCLUDE => (string) json_encode([
                        [
                            'ips' => $matchingIp,
                        ],
                    ]),
                ],
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => $collectionHttpResponses,
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-3'],
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
        ?ServiceConfigurationModel $serviceConfiguration,
        array $httpResponseDataCollection,
        callable $assertions
    ): void {
        $serviceId = $input['--service-id'] ?? '';
        $serviceId = is_string($serviceId) ? $serviceId : '';

        $this->mockServiceConfiguration($serviceId, $serviceConfiguration);

        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->mockHandler->append($this->httpResponseFactory->createFromArray($httpResponseData));
        }

        $output = new BufferedOutput();

        $commandReturnCode = $this->command->run(new ArrayInput($input), $output);

        $assertions($commandReturnCode, $output->fetch());
    }

    private function mockServiceConfiguration(
        string $serviceId,
        ?ServiceConfigurationModel $serviceConfigurationModel
    ): void {
        $serviceConfiguration = \Mockery::mock(ServiceConfiguration::class);
        $serviceConfiguration
            ->shouldReceive('getServiceConfiguration')
            ->with($serviceId)
            ->andReturn($serviceConfigurationModel)
        ;

        ObjectReflector::setProperty(
            $this->command,
            AbstractInstanceListCommand::class,
            'serviceConfiguration',
            $serviceConfiguration
        );
    }
}
