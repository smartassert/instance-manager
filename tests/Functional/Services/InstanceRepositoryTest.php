<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Model\Instance;
use App\Services\InstanceRepository;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\Droplet;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @phpstan-import-type HttpResponseData from HttpResponseFactory
 */
class InstanceRepositoryTest extends KernelTestCase
{
    private InstanceRepository $instanceRepository;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $instanceRepository = self::getContainer()->get(InstanceRepository::class);
        \assert($instanceRepository instanceof InstanceRepository);
        $this->instanceRepository = $instanceRepository;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    public function testCreate(): void
    {
        $dropletData = [
            'id' => 123,
            'created_at' => '2020-01-02T01:02:03.000Z',
        ];

        $successResponseData = [
            HttpResponseFactory::KEY_STATUS_CODE => 202,
            HttpResponseFactory::KEY_HEADERS => [
                'content-type' => 'application/json; charset=utf-8',
            ],
            HttpResponseFactory::KEY_BODY => (string) json_encode([
                'droplet' => $dropletData,
            ]),
        ];

        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray($successResponseData)
        );
        $instance = $this->instanceRepository->create('service_id', 123465, '');

        self::assertEquals(
            new Instance(new Droplet($dropletData)),
            $instance
        );
    }

    /**
     * @dataProvider findAllDataProvider
     *
     * @param Instance[] $expectedInstances
     */
    public function testFindAll(string $httpResponseBody, array $expectedInstances): void
    {
        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray([
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json; charset=utf-8',
                ],
                HttpResponseFactory::KEY_BODY => $httpResponseBody,
            ])
        );

        $instances = $this->instanceRepository->findAll('service_id');

        self::assertCount(count($expectedInstances), $instances);

        foreach ($instances as $instanceIndex => $instance) {
            $expectedInstance = $expectedInstances[$instanceIndex];
            self::assertSame($expectedInstance->getId(), $instance->getId());
        }
    }

    /**
     * @return array<mixed>
     */
    public function findAllDataProvider(): array
    {
        return [
            'none' => [
                'httpResponseBody' => (string) json_encode([
                    'droplets' => [],
                ]),
                'expectedInstances' => [],
            ],
            'one' => [
                'httpResponseBody' => (string) json_encode([
                    'droplets' => [
                        [
                            'id' => 123,
                        ],
                    ],
                ]),
                'expectedInstances' => [
                    new Instance(new Droplet(['id' => 123])),
                ],
            ],
            'many' => [
                'httpResponseBody' => (string) json_encode([
                    'droplets' => [
                        [
                            'id' => 123,
                        ],
                        [
                            'id' => 456,
                        ],
                        [
                            'id' => 789,
                        ],
                    ],
                ]),
                'expectedInstances' => [
                    new Instance(new Droplet(['id' => 123])),
                    new Instance(new Droplet(['id' => 456])),
                    new Instance(new Droplet(['id' => 789])),
                ],
            ],
        ];
    }

    /**
     * @dataProvider findCurrentDataProvider
     */
    public function testFindCurrent(string $httpResponseBody, ?Instance $expectedInstance): void
    {
        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray([
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json; charset=utf-8',
                ],
                HttpResponseFactory::KEY_BODY => $httpResponseBody,
            ])
        );

        $instance = $this->instanceRepository->findCurrent('service_id', 123456);
        self::assertEquals($expectedInstance, $instance);
    }

    /**
     * @return array<mixed>
     */
    public function findCurrentDataProvider(): array
    {
        return [
            'not found' => [
                'httpResponseBody' => (string) json_encode([
                    'droplets' => [],
                ]),
                'expectedInstance' => null,
            ],
            'single droplet' => [
                'httpResponseBody' => (string) json_encode([
                    'droplets' => [
                        [
                            'id' => 123,
                            'created_at' => '2021-07-28T16:36:31Z',
                        ],
                    ],
                ]),
                'expectedInstance' => new Instance(new Droplet([
                    'id' => 123,
                    'created_at' => '2021-07-28T16:36:31Z',
                ])),
            ],
            'multiple droplets' => [
                'httpResponseBody' => (string) json_encode([
                    'droplets' => [
                        [
                            'id' => 123,
                            'created_at' => '2021-07-28T16:36:31Z',
                        ],
                        [
                            'id' => 456,
                            'created_at' => '2021-07-29T16:36:31Z',
                        ],
                        [
                            'id' => 798,
                            'created_at' => '2021-07-30T16:36:31Z',
                        ],
                    ],
                ]),
                'expectedInstance' => new Instance(new Droplet([
                    'id' => 798,
                    'created_at' => '2021-07-30T16:36:31Z',
                ])),
            ],
        ];
    }

    /**
     * @dataProvider findDataProvider
     *
     * @param HttpResponseData $httpResponseData
     */
    public function testFind(array $httpResponseData, int $id, ?Instance $expectedInstance): void
    {
        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray($httpResponseData)
        );

        $instance = $this->instanceRepository->find($id);
        self::assertEquals($expectedInstance, $instance);
    }

    /**
     * @return array<mixed>
     */
    public function findDataProvider(): array
    {
        return [
            'not found' => [
                'httpResponseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 404,
                ],
                'id' => 0,
                'expectedInstance' => null,
            ],
            'found' => [
                'httpResponseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 200,
                    HttpResponseFactory::KEY_HEADERS => [
                        'content-type' => 'application/json; charset=utf-8',
                    ],
                    HttpResponseFactory::KEY_BODY => (string) json_encode([
                        'droplet' => [
                            'id' => 123,
                            'created_at' => '2020-01-02T01:02:03.000Z',
                        ],
                    ]),
                ],
                'id' => 123,
                'expectedInstance' => new Instance(
                    new Droplet([
                        'id' => 123,
                        'created_at' => '2020-01-02T01:02:03.000Z',
                    ]),
                ),
            ],
        ];
    }

    /**
     * @dataProvider deleteDataProvider
     *
     * @param HttpResponseData $httpResponseData
     */
    public function testDelete(array $httpResponseData, int $id): void
    {
        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray($httpResponseData)
        );

        $this->instanceRepository->delete($id);

        self::expectNotToPerformAssertions();
    }

    /**
     * @return array<mixed>
     */
    public function deleteDataProvider(): array
    {
        return [
            'not found' => [
                'httpResponseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 404,
                ],
                'id' => 0,
            ],
            'found' => [
                'httpResponseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 204,
                ],
                'id' => 123,
            ],
        ];
    }
}
