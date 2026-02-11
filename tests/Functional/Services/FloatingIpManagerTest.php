<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Model\AssignedIp;
use App\Model\Instance;
use App\Services\FloatingIpManager;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\Action as ActionEntity;
use DigitalOceanV2\Entity\Droplet;
use DigitalOceanV2\Entity\FloatingIp as FloatingIpEntity;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @phpstan-import-type HttpResponseData from HttpResponseFactory
 */
class FloatingIpManagerTest extends KernelTestCase
{
    private FloatingIpManager $floatingIpManager;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $floatingIpManager = self::getContainer()->get(FloatingIpManager::class);
        \assert($floatingIpManager instanceof FloatingIpManager);
        $this->floatingIpManager = $floatingIpManager;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    /**
     * @dataProvider createDataProvider
     *
     * @param HttpResponseData $httpResponseData
     */
    public function testCreate(
        array $httpResponseData,
        Instance $instance,
        AssignedIp $expectedAssignedIp
    ): void {
        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray($httpResponseData)
        );

        $floatingIP = $this->floatingIpManager->create($instance);

        self::assertEquals($expectedAssignedIp, $floatingIP);
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        return [
            'no existing floating IP' => [
                'httpResponseData' => [
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
                'instance' => new Instance(
                    new Droplet(['id' => 123])
                ),
                'expectedAssignedIp' => new AssignedIp(
                    new FloatingIpEntity([
                        'ip' => '127.0.0.100',
                        'droplet' => [
                            'id' => 123,
                        ],
                    ])
                ),
            ],
        ];
    }

    /**
     * @dataProvider reAssignDataProvider
     *
     * @param HttpResponseData $httpResponseData
     */
    public function testReAssign(
        array $httpResponseData,
        Instance $instance,
        string $ip,
        ActionEntity $expectedActionEntity
    ): void {
        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray($httpResponseData)
        );

        $action = $this->floatingIpManager->reAssign($instance, $ip);

        self::assertEquals($expectedActionEntity, $action);
    }

    /**
     * @return array<mixed>
     */
    public function reAssignDataProvider(): array
    {
        return [
            'success' => [
                'httpResponseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 202,
                    HttpResponseFactory::KEY_HEADERS => [
                        'content-type' => 'application/json; charset=utf-8',
                    ],
                    HttpResponseFactory::KEY_BODY => (string) json_encode([
                        'action' => [
                            'id' => 001,
                            'type' => 'assign_ip',
                        ],
                    ]),
                ],
                'instance' => new Instance(new Droplet(['id' => 123])),
                'ip' => '127.0.0.1',
                'expectedActionEntity' => new ActionEntity([
                    'id' => 001,
                    'type' => 'assign_ip',
                ]),
            ],
        ];
    }
}
