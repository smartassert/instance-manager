<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Model\InstanceCollection;
use App\Model\ServiceConfiguration;
use App\Services\InstanceCollectionHydrator;
use App\Tests\Services\DropletDataFactory;
use App\Tests\Services\HttpResponseDataFactory;
use App\Tests\Services\HttpResponseFactory;
use App\Tests\Services\InstanceFactory;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InstanceCollectionHydratorTest extends KernelTestCase
{
    private InstanceCollectionHydrator $instanceCollectionHydrator;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $instanceCollectionHydrator = self::getContainer()->get(InstanceCollectionHydrator::class);
        \assert($instanceCollectionHydrator instanceof InstanceCollectionHydrator);
        $this->instanceCollectionHydrator = $instanceCollectionHydrator;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    public function testHydrate(): void
    {
        $instanceCollectionData = [
            123 => [
                'ipAddress' => '127.0.0.1',
                'createdAt' => '2020-01-02T01:01:01.000Z',
                'state' => [
                    'version' => '0.1',
                    'message-queue-size' => 14,
                    'key1' => 'value1',
                ],
            ],
            456 => [
                'ipAddress' => '127.0.0.2',
                'createdAt' => '2020-01-02T02:02:02.000Z',
                'state' => [
                    'version' => '0.2',
                    'message-queue-size' => 7,
                    'key2' => 'value2',
                ],
            ],
        ];

        $expectedStateData = [
            123 => [
                'version' => '0.1',
                'message-queue-size' => 14,
                'key1' => 'value1',
                'ips' => [
                    '127.0.0.1',
                ],
                'created_at' => '2020-01-02T01:01:01.000Z',
            ],
            456 => [
                'version' => '0.2',
                'message-queue-size' => 7,
                'key2' => 'value2',
                'ips' => [
                    '127.0.0.2',
                ],
                'created_at' => '2020-01-02T02:02:02.000Z',
            ],
        ];

        $instances = [];
        foreach ($instanceCollectionData as $dropletId => $instanceData) {
            $instances[] = InstanceFactory::create(array_merge(
                DropletDataFactory::createWithIps($dropletId, [$instanceData['ipAddress']]),
                [
                    'created_at' => $instanceData['createdAt'],
                ]
            ));
            $this->mockHandler->append(
                $this->httpResponseFactory->createFromArray(
                    HttpResponseDataFactory::createJsonResponseData($instanceData['state'])
                ),
            );
        }

        $serviceConfiguration = new ServiceConfiguration(
            'service_id',
            'https://{{ host }}/health-check',
            'https://{{ host }}/state'
        );
        $instanceCollection = new InstanceCollection($instances);
        $hydratedCollection = $this->instanceCollectionHydrator->hydrate($serviceConfiguration, $instanceCollection);

        foreach ($hydratedCollection as $hydratedInstance) {
            $expectedInstanceStateData = $expectedStateData[$hydratedInstance->getId()];

            self::assertSame($expectedInstanceStateData, $hydratedInstance->getState());
        }
    }
}
