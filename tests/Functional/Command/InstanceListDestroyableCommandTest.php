<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\AbstractInstanceListCommand;
use App\Command\InstanceListDestroyableCommand;
use App\Command\Option;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;
use App\Services\ServiceConfiguration;
use App\Tests\Services\HttpResponseDataFactory;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

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
                '--' . Option::OPTION_SERVICE_ID => 'service_id',
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
            'empty service id' => [
                'input' => [],
                'expectedReturnCode' => AbstractInstanceListCommand::EXIT_CODE_EMPTY_SERVICE_ID,
            ],
        ];
    }

    /**
     * @dataProvider runDataProvider
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
        $serviceId = $input['--service-id'] ?? '';
        $serviceId = is_string($serviceId) ? $serviceId : '';

        $this->mockServiceConfiguration($serviceId, $serviceConfiguration);

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
        $serviceId = 'service_id';

        $serviceConfiguration = new ServiceConfigurationModel(
            $serviceId,
            'https://{{ host }}/health-check',
            'https://{{ host }}/state'
        );

        $excludedIp = '127.0.0.1';

        $dropletData = [
            'instance-with-excluded-ip' => [
                'id' => 1,
                'created_at' => '2020-01-02T01:01:01.000Z',
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
                'created_at' => '2020-01-02T02:02:02.000Z',
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
                'created_at' => '2020-01-02T03:03:03.000Z',
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
            'droplets' => HttpResponseDataFactory::createJsonResponseData([
                'droplets' => array_values($dropletData),
            ]),
            '1-state' => HttpResponseDataFactory::createJsonResponseData(
                $stateResponseData['instance-with-excluded-ip']
            ),
            '2-state' => HttpResponseDataFactory::createJsonResponseData($stateResponseData['instance-not-idle']),
            '3-state' => HttpResponseDataFactory::createJsonResponseData($stateResponseData['instance-is-idle']),
            '4-state' => HttpResponseDataFactory::createJsonResponseData($stateResponseData['instance-null-idle']),
        ];

        $expectedOutputData = [
            'instance-with-excluded-ip' => [
                'id' => 1,
                'state' => array_merge(
                    [
                        'ips' => [
                            $excludedIp,
                        ],
                        'created_at' => '2020-01-02T01:01:01.000Z',
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
                        'created_at' => '2020-01-02T02:02:02.000Z',
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
                        'created_at' => '2020-01-02T03:03:03.000Z',
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
                        'created_at' => '2020-01-02T04:04:04.000Z',
                    ],
                    $stateResponseData['instance-null-idle']
                ),
            ],
        ];

        $input = [
            '--' . Option::OPTION_SERVICE_ID => 'service_id',
            '--excluded-ip' => $excludedIp,
        ];

        return [
            'no instances' => [
                'input' => $input,
                'serviceConfiguration' => $serviceConfiguration,
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
                'serviceConfiguration' => $serviceConfiguration,
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
                'serviceConfiguration' => $serviceConfiguration,
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
                'serviceConfiguration' => $serviceConfiguration,
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
                'serviceConfiguration' => $serviceConfiguration,
                'httpResponseDataCollection' => $collectionHttpResponses,
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    $expectedOutputData['instance-is-idle'],
                ]),
            ],
        ];
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
