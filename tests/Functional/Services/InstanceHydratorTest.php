<?php

namespace App\Tests\Functional\Services;

use App\Services\InstanceHydrator;
use App\Tests\Services\DropletDataFactory;
use App\Tests\Services\HttpResponseFactory;
use App\Tests\Services\InstanceFactory;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InstanceHydratorTest extends KernelTestCase
{
    private InstanceHydrator $instanceHydrator;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $instanceHydrator = self::getContainer()->get(InstanceHydrator::class);
        \assert($instanceHydrator instanceof InstanceHydrator);
        $this->instanceHydrator = $instanceHydrator;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    public function testHydrate(): void
    {
        $stateData = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray([
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json',
                ],
                HttpResponseFactory::KEY_BODY => json_encode($stateData),
            ])
        );

        $ips = ['127.0.0.1', '10.0.0.1'];
        $instance = InstanceFactory::create(DropletDataFactory::createWithIps(123, $ips));

        $instance = $this->instanceHydrator->hydrate($instance);
        self::assertSame(
            array_merge(
                $stateData,
                [
                    'ips' => $ips,
                ]
            ),
            $instance->getState()
        );
    }
}
