<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Services\ActionRepository;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\Action as ActionEntity;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ActionRepositoryTest extends KernelTestCase
{
    private ActionRepository $actionRepository;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $actionRepository = self::getContainer()->get(ActionRepository::class);
        \assert($actionRepository instanceof ActionRepository);
        $this->actionRepository = $actionRepository;

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
     * @param array<mixed> $httpResponseData
     */
    public function testFind(array $httpResponseData, int $id, ?ActionEntity $expectedAction): void
    {
        $this->mockHandler->append($this->httpResponseFactory->createFromArray($httpResponseData));

        self::assertEquals($expectedAction, $this->actionRepository->find($id));
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
                'id' => 1,
                'expectedAction' => null,
            ],
            'found' => [
                'httpResponseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 200,
                    HttpResponseFactory::KEY_HEADERS => [
                        'content-type' => 'application/json; charset=utf-8',
                    ],
                    HttpResponseFactory::KEY_BODY => json_encode([
                        'action' => [
                            'id' => 2,
                        ],
                    ]),
                ],
                'id' => 2,
                'expectedAction' => new ActionEntity([
                    'id' => 2,
                ]),
            ],
        ];
    }
}
