<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Model\InstanceCollection;
use App\Model\ServiceConfiguration;
use App\Services\InstanceCollectionHydrator;
use App\Tests\Services\DropletDataFactory;
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
                'state' => [
                    'version' => '0.1',
                    'message-queue-size' => 14,
                    'key1' => 'value1',
                ],
            ],
            456 => [
                'ipAddress' => '127.0.0.2',
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
            ],
            456 => [
                'version' => '0.2',
                'message-queue-size' => 7,
                'key2' => 'value2',
                'ips' => [
                    '127.0.0.2',
                ],
            ],
        ];

        $instances = [];
        foreach ($instanceCollectionData as $dropletId => $instanceData) {
            $instances[] = InstanceFactory::create(
                DropletDataFactory::createWithIps($dropletId, [$instanceData['ipAddress']])
            );
            $this->mockHandler->append(
                $this->httpResponseFactory->createFromArray([
                    HttpResponseFactory::KEY_STATUS_CODE => 200,
                    HttpResponseFactory::KEY_HEADERS => [
                        'content-type' => 'application/json',
                    ],
                    HttpResponseFactory::KEY_BODY => json_encode($instanceData['state']),
                ])
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
