<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Model\ServiceConfiguration;
use App\Services\InstanceClient;
use App\Tests\Services\HttpResponseFactory;
use App\Tests\Services\InstanceFactory;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InstanceClientTest extends KernelTestCase
{
    private InstanceClient $instanceClient;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $instanceClient = self::getContainer()->get(InstanceClient::class);
        \assert($instanceClient instanceof InstanceClient);
        $this->instanceClient = $instanceClient;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    public function testGetHealth(): void
    {
        $response = $this->httpResponseFactory->createFromArray([
            HttpResponseFactory::KEY_STATUS_CODE => 200,
            HttpResponseFactory::KEY_HEADERS => [
                'content-type' => 'application/json',
            ],
            HttpResponseFactory::KEY_BODY => (string) json_encode([]),
        ]);

        $this->mockHandler->append($response);

        $serviceConfiguration = new ServiceConfiguration('service_id', 'https://{{ host }}/health-check', '');
        $instance = InstanceFactory::create(['id' => 123]);

        self::assertSame(
            $response,
            $this->instanceClient->getHealth($serviceConfiguration, $instance)
        );
    }

    /**
     * @dataProvider getStateDataProvider
     *
     * @param array<mixed> $responseData
     * @param array<mixed> $expectedState
     */
    public function testGetState(string $stateUrl, array $responseData, array $expectedState): void
    {
        $this->mockHandler->append($this->httpResponseFactory->createFromArray($responseData));

        $serviceConfiguration = new ServiceConfiguration('service_id', '', 'https://{{ host }}/state');
        $instance = InstanceFactory::create(['id' => 123]);

        self::assertSame(
            $expectedState,
            $this->instanceClient->getState($serviceConfiguration, $instance)
        );
    }

    /**
     * @return array<mixed>
     */
    public function getStateDataProvider(): array
    {
        $data = [
            'string' => 'content',
            'boolean' => true,
            'int' => 123,
            'float' => M_PI,
            'array' => [
                'key1' => 'value1',
                'key2' => 'value2',
                'key3' => 'value3',
            ],
        ];

        return [
            'response not an array' => [
                'stateUrl' => '/state',
                'responseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 200,
                    HttpResponseFactory::KEY_BODY => 'string content',
                ],
                'expectedState' => [],
            ],
            'response content type not "application/json"' => [
                'stateUrl' => '/state',
                'responseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 200,
                    HttpResponseFactory::KEY_BODY => (string) json_encode($data),
                ],
                'expectedState' => [],
            ],
            'response is json array, content type is "application/json"' => [
                'stateUrl' => '/state',
                'responseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 200,
                    HttpResponseFactory::KEY_HEADERS => [
                        'content-type' => 'application/json',
                    ],
                    HttpResponseFactory::KEY_BODY => (string) json_encode($data),
                ],
                'expectedState' => $data,
            ],
            'response is json array, content type is "application/json; charset=UTF-8"' => [
                'stateUrl' => '/state',
                'responseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 200,
                    HttpResponseFactory::KEY_HEADERS => [
                        'content-type' => 'application/json; charset=UTF-8',
                    ],
                    HttpResponseFactory::KEY_BODY => (string) json_encode($data),
                ],
                'expectedState' => $data,
            ],
        ];
    }
}
