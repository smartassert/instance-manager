<?php

namespace App\Tests\Functional\Services;

use App\Services\ImageRepository;
use App\Tests\Services\HttpResponseFactory;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ImageRepositoryTest extends KernelTestCase
{
    private ImageRepository $imageRepository;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $imageRepository = self::getContainer()->get(ImageRepository::class);
        \assert($imageRepository instanceof ImageRepository);
        $this->imageRepository = $imageRepository;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    /**
     * @dataProvider existsDataProvider
     *
     * @param array<mixed> $httpResponseData
     */
    public function testExists(array $httpResponseData, int|string $imageId, bool $expectedExists): void
    {
        $this->mockHandler->append(
            $this->httpResponseFactory->createFromArray($httpResponseData)
        );

        self::assertSame($expectedExists, $this->imageRepository->exists($imageId));
    }

    /**
     * @return array<mixed>
     */
    public function existsDataProvider(): array
    {
        return [
            'not found' => [
                'httpResponseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 404,
                ],
                'id' => 0,
                'expectedExists' => false,
            ],
            'found' => [
                'httpResponseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 200,
                    HttpResponseFactory::KEY_HEADERS => [
                        'content-type' => 'application/json; charset=utf-8',
                    ],
                    HttpResponseFactory::KEY_BODY => (string) json_encode([
                        'image' => [
                            'id' => 123,
                        ],
                    ]),
                ],
                'id' => 123,
                'expectedExists' => true,
            ],
        ];
    }
}
