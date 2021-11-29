<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Model\AssignedIp;
use App\Services\FloatingIpRepository;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\FloatingIp as FloatingIpEntity;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FloatingIpRepositoryTest extends KernelTestCase
{
    private const COLLECTION_TAG = 'service_id';

    private FloatingIpRepository $floatingIpRepository;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $floatingIpRepository = self::getContainer()->get(FloatingIpRepository::class);
        \assert($floatingIpRepository instanceof FloatingIpRepository);
        $this->floatingIpRepository = $floatingIpRepository;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    /**
     * @dataProvider findDataProvider
     *
     * @param array<mixed> $floatingIpResponseData
     */
    public function testFind(array $floatingIpResponseData, ?AssignedIp $expectedAssignedIp): void
    {
        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray([
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json; charset=utf-8',
                ],
                HttpResponseFactory::KEY_BODY => (string) json_encode([
                    'floating_ips' => $floatingIpResponseData,
                ]),
            ])
        );

        self::assertEquals($expectedAssignedIp, $this->floatingIpRepository->find(self::COLLECTION_TAG));
    }

    /**
     * @return array<mixed>
     */
    public function findDataProvider(): array
    {
        return [
            'none' => [
                'floatingIpResponseData' => [],
                'expectedAssignedIp' => null,
            ],
            'one, not assigned to anything' => [
                'floatingIpResponseData' => [
                    [
                        'ip' => '127.0.0.100',
                        'droplet' => null,
                    ],
                ],
                'expectedAssignedIp' => null,
            ],
            'one, assigned to an instance' => [
                'floatingIpResponseData' => [
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
                'expectedAssignedIp' => new AssignedIp(
                    new FloatingIpEntity([
                        'ip' => '127.0.0.200',
                        'droplet' => (object) [
                            'id' => 123,
                            'tags' => [
                                self::COLLECTION_TAG,
                            ],
                        ],
                    ])
                ),
            ],
            'two, first assigned to an instance' => [
                'floatingIpResponseData' => [
                    [
                        'ip' => '127.0.0.300',
                        'droplet' => [
                            'id' => 123,
                            'tags' => [
                                self::COLLECTION_TAG,
                            ],
                        ],
                    ],
                    [
                        'ip' => '127.0.0.301',
                        'droplet' => [
                            'id' => 465,
                            'tags' => [],
                        ],
                    ],
                ],
                'expectedAssignedIp' => new AssignedIp(
                    new FloatingIpEntity([
                        'ip' => '127.0.0.300',
                        'droplet' => (object) [
                            'id' => 123,
                            'tags' => [
                                self::COLLECTION_TAG,
                            ],
                        ],
                    ])
                ),
            ],
            'two, second assigned to an instance' => [
                'floatingIpResponseData' => [
                    [
                        'ip' => '127.0.0.400',
                        'droplet' => [
                            'id' => 123,
                            'tags' => [],
                        ],
                    ],
                    [
                        'ip' => '127.0.0.401',
                        'droplet' => [
                            'id' => 465,
                            'tags' => [
                                self::COLLECTION_TAG,
                            ],
                        ],
                    ],
                ],
                'expectedAssignedIp' => new AssignedIp(
                    new FloatingIpEntity([
                        'ip' => '127.0.0.401',
                        'droplet' => (object) [
                            'id' => 465,
                            'tags' => [
                                self::COLLECTION_TAG,
                            ],
                        ],
                    ])
                ),
            ],
        ];
    }
}
