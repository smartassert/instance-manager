<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceDestroyExpiredCommand;
use App\Command\Option;
use App\Tests\Services\HttpResponseFactory;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @phpstan-import-type HttpResponseData from HttpResponseFactory
 */
class InstanceDestroyExpiredCommandTest extends KernelTestCase
{
    use MissingServiceIdTestTrait;

    private const COLLECTION_TAG = 'service_id';

    private InstanceDestroyExpiredCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(InstanceDestroyExpiredCommand::class);
        \assert($command instanceof InstanceDestroyExpiredCommand);
        $this->command = $command;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    /**
     * @dataProvider runSuccessDataProvider
     *
     * @param array<int, HttpResponseData> $httpResponseDataCollection
     */
    public function testRunSuccess(
        array $httpResponseDataCollection,
        string $expectedOutput,
    ): void {
        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->mockHandler->append($this->httpResponseFactory->createFromArray($httpResponseData));
        }

        $output = new BufferedOutput();
        $input = new ArrayInput([
            '--' . Option::OPTION_SERVICE_ID => self::COLLECTION_TAG,
        ]);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame($expectedOutput, $output->fetch());
    }

    /**
     * @return array<mixed>
     */
    public function runSuccessDataProvider(): array
    {
        $publicIp = '127.0.0.0';

        return [
            'no instances' => [
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
                'expectedOutput' => '',
            ],
            'single instance, no public ip' => [
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                $this->createResponseDropletData(1),
                            ],
                        ]),
                    ],
                ],
                'expectedOutput' => '',
            ],
            'single instance, has public ip' => [
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                $this->createResponseDropletData(1, $publicIp),
                            ],
                        ]),
                    ],
                ],
                'expectedOutput' => '',
            ],
            'two instances, no public ip' => [
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                $this->createResponseDropletData(1),
                                $this->createResponseDropletData(2),
                            ],
                        ]),
                    ],
                    'floating_ips' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'floating_ips' => [],
                        ]),
                    ],
                ],
                'expectedOutput' => '{"status":"error","error-code":"no-ip"}',
            ],
            'two instances, public ip exists, not assigned' => [
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                $this->createResponseDropletData(1),
                                $this->createResponseDropletData(2),
                            ],
                        ]),
                    ],
                    'floating_ips' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'floating_ips' => [
                                [
                                    'ip' => '127.0.0.255',
                                    'droplet' => null,
                                ],
                            ],
                        ]),
                    ],
                ],
                'expectedOutput' => '{"status":"error","error-code":"no-ip"}',
            ],
            'two instances, public ip exists, assigned to oldest instance' => [
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                $this->createResponseDropletData(1, $publicIp),
                                $this->createResponseDropletData(2),
                            ],
                        ]),
                    ],
                    'floating_ips' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'floating_ips' => [
                                [
                                    'ip' => $publicIp,
                                    'droplet' => $this->createResponseDropletData(1, $publicIp),
                                ],
                            ],
                        ]),
                    ],
                ],
                'expectedOutput' => '',
            ],
            'two instances, public ip exists, assigned to newest instance' => [
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                $this->createResponseDropletData(1),
                                $this->createResponseDropletData(2, $publicIp),
                            ],
                        ]),
                    ],
                    'floating_ips' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'floating_ips' => [
                                [
                                    'ip' => $publicIp,
                                    'droplet' => $this->createResponseDropletData(2, $publicIp),
                                ],
                            ],
                        ]),
                    ],
                    'instance-1-destroy-response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 204,
                    ],
                ],
                'expectedOutput' => json_encode([
                    [
                        'id' => 1,
                        'state' => [
                            'ips' => [
                                '127.0.0.1',
                            ],
                            'created_at' => '2020-01-02T01:01:01.000Z',
                        ],
                    ],
                ]),
            ],
            'three instances, public ip exists, assigned to newest instance' => [
                'httpResponseDataCollection' => [
                    'droplets' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'droplets' => [
                                $this->createResponseDropletData(1),
                                $this->createResponseDropletData(2),
                                $this->createResponseDropletData(3, $publicIp),
                            ],
                        ]),
                    ],
                    'floating_ips' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 200,
                        HttpResponseFactory::KEY_HEADERS => [
                            'content-type' => 'application/json; charset=utf-8',
                        ],
                        HttpResponseFactory::KEY_BODY => (string) json_encode([
                            'floating_ips' => [
                                [
                                    'ip' => $publicIp,
                                    'droplet' => $this->createResponseDropletData(3, $publicIp),
                                ],
                            ],
                        ]),
                    ],
                    'instance-1-destroy-response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 204,
                    ],
                    'instance-2-destroy-response' => [
                        HttpResponseFactory::KEY_STATUS_CODE => 204,
                    ],
                ],
                'expectedOutput' => json_encode([
                    [
                        'id' => 1,
                        'state' => [
                            'ips' => [
                                '127.0.0.1',
                            ],
                            'created_at' => '2020-01-02T01:01:01.000Z',
                        ],
                    ],
                    [
                        'id' => 2,
                        'state' => [
                            'ips' => [
                                '127.0.0.2',
                            ],
                            'created_at' => '2020-01-02T02:02:02.000Z',
                        ],
                    ],
                ]),
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function createResponseDropletData(int $id, ?string $ip = null): array
    {
        $ip = is_string($ip) ? $ip : sprintf('127.0.0.%d', $id);

        return [
            'id' => $id,
            'created_at' => sprintf('2020-01-02T0%d:0%d:0%d.000Z', $id, $id, $id),
            'networks' => [
                'v4' => [
                    [
                        'ip_address' => $ip,
                    ],
                ],
            ],
            'tags' => [self::COLLECTION_TAG, sprintf('instance-%d', $id)],
        ];
    }
}
