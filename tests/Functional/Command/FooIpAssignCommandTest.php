<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\FooIpAssignCommand;
use App\Command\Option;
use App\Exception\ActionTimeoutException;
use App\Services\ActionRunner;
use App\Services\ServiceConfiguration;
use App\Tests\Services\DropletDataFactory;
use App\Tests\Services\HttpResponseFactory;
use GuzzleHttp\Handler\MockHandler;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

class FooIpAssignCommandTest extends KernelTestCase
{
    use MockeryPHPUnitIntegration;

    private const SERVICE_ID = 'service_id';
    private const IMAGE_ID = '123456';

    private FooIpAssignCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(FooIpAssignCommand::class);
        \assert($command instanceof FooIpAssignCommand);
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
            'empty service id' => [
                'input' => [],
                'expectedReturnCode' => FooIpAssignCommand::EXIT_CODE_EMPTY_SERVICE_ID,
            ],
        ];
    }

    public function testRunImageIdMissing(): void
    {
        $input = new ArrayInput([
            '--' . Option::OPTION_SERVICE_ID => self::SERVICE_ID,
        ]);

        $output = new BufferedOutput();

        $this->mockServiceConfigurationGetImageId(null);

        $commandReturnCode = $this->command->run($input, $output);

        self::assertSame(FooIpAssignCommand::EXIT_CODE_MISSING_IMAGE_ID, $commandReturnCode);
    }

    /**
     * @dataProvider runSuccessDataProvider
     *
     * @param array<int, array<mixed>> $httpResponseDataCollection
     */
    public function testRunSuccess(
        ?callable $setup,
        array $httpResponseDataCollection,
        int $expectedExitCode,
        string $expectedOutput
    ): void {
        if (is_callable($setup)) {
            $setup($this->command);
        }

        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->mockHandler->append(
                $this->httpResponseFactory->createFromArray($httpResponseData)
            );
        }

        $output = new BufferedOutput();
        $input = new ArrayInput([
            '--' . Option::OPTION_SERVICE_ID => self::SERVICE_ID,
        ]);

        $this->mockServiceConfigurationGetImageId(self::IMAGE_ID);

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
                'setup' => null,
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
                'expectedExitCode' => FooIpAssignCommand::EXIT_CODE_NO_CURRENT_INSTANCE,
                'expectedOutput' => (string) json_encode([
                    'status' => 'error',
                    'error-code' => 'no-instance',
                ]),
            ],
            'ip is created' => [
                'setup' => null,
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
                    'outcome' => 'create',
                    'source-instance' => null,
                ]),
            ],
            'creation timed out' => [
                'setup' => function (FooIpAssignCommand $command) {
                    $actionRunner = \Mockery::mock(ActionRunner::class);
                    $actionRunner
                        ->shouldReceive('run')
                        ->andThrow(new ActionTimeoutException())
                    ;

                    ObjectReflector::setProperty(
                        $command,
                        FooIpAssignCommand::class,
                        'actionRunner',
                        $actionRunner
                    );
                },
                'httpResponseDataCollection' => [
                    'droplets response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                [
                                    'id' => 456,
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
                ],
                'expectedExitCode' => FooIpAssignCommand::EXIT_CODE_ACTION_TIMED_OUT,
                'expectedOutput' => (string) json_encode([
                    'status' => 'error',
                    'error-code' => 'create-timed-out',
                    'ip' => '127.0.0.100',
                    'source-instance' => null,
                    'target-instance' => 456,
                    'timeout-in-seconds' => 30,
                ]),
            ],
            'ip already assigned to current instance' => [
                'setup' => null,
                'httpResponseDataCollection' => [
                    'droplets response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                DropletDataFactory::createWithIps(123, ['127.0.0.200']),
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
                                            self::SERVICE_ID,
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ],
                ],
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    'status' => 'success',
                    'outcome' => 'assign',
                    'ip' => '127.0.0.200',
                    'source-instance' => 123,
                    'target-instance' => 123,
                ]),
            ],
            'ip is re-assigned' => [
                'setup' => null,
                'httpResponseDataCollection' => [
                    'droplets response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                [
                                    'id' => 456,
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
                                            self::SERVICE_ID,
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ],
                    're-assign response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'action' => [
                                'id' => 789,
                                'type' => 'assign_ip',
                                'status' => 'in-progress',
                            ],
                        ]),
                    ],
                    'action status check response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'action' => [
                                'id' => 789,
                                'type' => 'assign_ip',
                                'status' => 'completed',
                            ],
                        ]),
                    ],
                ],
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    'status' => 'success',
                    'outcome' => 'assign',
                    'ip' => '127.0.0.200',
                    'source-instance' => 123,
                    'target-instance' => 456,
                ]),
            ],
            're-assignment timed out' => [
                'setup' => function (FooIpAssignCommand $command) {
                    $actionRunner = \Mockery::mock(ActionRunner::class);
                    $actionRunner
                        ->shouldReceive('run')
                        ->andThrow(new ActionTimeoutException())
                    ;

                    ObjectReflector::setProperty(
                        $command,
                        FooIpAssignCommand::class,
                        'actionRunner',
                        $actionRunner
                    );
                },
                'httpResponseDataCollection' => [
                    'droplets response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                [
                                    'id' => 456,
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
                                            self::SERVICE_ID,
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ],
                    're-assign response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'action' => [
                                'id' => 789,
                                'type' => 'assign_ip',
                                'status' => 'in-progress',
                            ],
                        ]),
                    ],
                    'action status check response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'action' => [
                                'id' => 789,
                                'type' => 'assign_ip',
                                'status' => 'completed',
                            ],
                        ]),
                    ],
                ],
                'expectedExitCode' => FooIpAssignCommand::EXIT_CODE_ACTION_TIMED_OUT,
                'expectedOutput' => (string) json_encode([
                    'status' => 'error',
                    'error-code' => 'assign-timed-out',
                    'ip' => '127.0.0.200',
                    'source-instance' => 123,
                    'target-instance' => 456,
                    'timeout-in-seconds' => 30,
                ]),
            ],
        ];
    }

    private function mockServiceConfigurationGetImageId(?string $imageId): void
    {
        $serviceConfiguration = \Mockery::mock(ServiceConfiguration::class);
        $serviceConfiguration
            ->shouldReceive('getImageId')
            ->with(self::SERVICE_ID)
            ->andReturn($imageId)
        ;

        ObjectReflector::setProperty(
            $this->command,
            $this->command::class,
            'serviceConfiguration',
            $serviceConfiguration
        );
    }
}
