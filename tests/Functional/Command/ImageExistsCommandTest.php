<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\ImageExistsCommand;
use App\Command\Option;
use App\Enum\Filename;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Services\ImageIdLoaderInterface;
use App\Services\ServiceConfiguration;
use App\Tests\Services\HttpResponseDataFactory;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

class ImageExistsCommandTest extends KernelTestCase
{
    use MissingServiceIdTestTrait;

    private const IMAGE_ID = '12345';

    private ImageExistsCommand $command;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(ImageExistsCommand::class);
        \assert($command instanceof ImageExistsCommand);
        $this->command = $command;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    /**
     * @dataProvider runThrowsExceptionDataProvider
     *
     * @param array<mixed>             $responseData
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    public function testRunThrowsException(
        array $responseData,
        string $expectedExceptionClass,
        string $expectedExceptionMessage,
        int $expectedExceptionCode
    ): void {
        $httpResponse = $this->httpResponseFactory->createFromArray($responseData);
        $this->setHttpResponse($httpResponse);

        self::expectException($expectedExceptionClass);
        self::expectExceptionMessage($expectedExceptionMessage);
        self::expectExceptionCode($expectedExceptionCode);

        $imageIdLoader = \Mockery::mock(ImageIdLoaderInterface::class);
        $imageIdLoader
            ->shouldReceive('load')
            ->with('service_id')
            ->andReturn(self::IMAGE_ID)
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'imageIdLoader', $imageIdLoader);

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => 'service_id',
            ]),
            new BufferedOutput()
        );
    }

    /**
     * @return array<mixed>
     */
    public function runThrowsExceptionDataProvider(): array
    {
        return [
            'invalid api token' => [
                'responseData' => [
                    HttpResponseFactory::KEY_STATUS_CODE => 401,
                ],
                'expectedExceptionClass' => RuntimeException::class,
                'expectedExceptionMessage' => 'Unauthorized',
                'expectedExceptionCode' => 401,
            ],
        ];
    }

    /**
     * @dataProvider runSuccessDataProvider
     *
     * @param array<mixed>             $input
     * @param array<int, array<mixed>> $httpResponseDataCollection
     */
    public function testRunSuccess(
        array $input,
        array $httpResponseDataCollection,
        int $expectedReturnCode
    ): void {
        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->setHttpResponse(
                $this->httpResponseFactory->createFromArray($httpResponseData)
            );
        }

        $imageIdLoader = \Mockery::mock(ImageIdLoaderInterface::class);
        $imageIdLoader
            ->shouldReceive('load')
            ->with('service_id')
            ->andReturn(self::IMAGE_ID)
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'imageIdLoader', $imageIdLoader);

        $commandReturnCode = $this->command->run(new ArrayInput($input), new NullOutput());

        self::assertSame($expectedReturnCode, $commandReturnCode);
    }

    /**
     * @return array<mixed>
     */
    public function runSuccessDataProvider(): array
    {
        return [
            'not exists' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                ],
                'httpResponseDataCollection' => [
                    [
                        HttpResponseFactory::KEY_STATUS_CODE => 404,
                    ],
                ],
                'expectedReturnCode' => Command::FAILURE,
            ],
            'exists' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                ],
                'httpResponseDataCollection' => [
                    HttpResponseDataFactory::createJsonResponseData([
                        'image' => [
                            'id' => 123,
                        ],
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
            ],
        ];
    }

    public function testRunWithoutServiceConfigurationFileThrowsException(): void
    {
        $serviceId = 'service_id';

        $this->expectExceptionObject(
            new ServiceConfigurationMissingException($serviceId, Filename::IMAGE->value)
        );

        $this->command->run(new ArrayInput(['--' . Option::OPTION_SERVICE_ID => $serviceId]), new NullOutput());
    }

    public function testRunWithoutImageIdThrowsException(): void
    {
        $serviceId = 'service_id';

        $exception = new ConfigurationFileValueMissingException(
            ServiceConfiguration::IMAGE_FILENAME,
            'image_id',
            'service_id'
        );

        $imageIdLoader = \Mockery::mock(ImageIdLoaderInterface::class);
        $imageIdLoader
            ->shouldReceive('load')
            ->with('service_id')
            ->andThrow($exception)
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'imageIdLoader', $imageIdLoader);

        $this->expectExceptionObject($exception);
        $this->command->run(new ArrayInput(['--' . Option::OPTION_SERVICE_ID => $serviceId]), new NullOutput());
    }

    private function setHttpResponse(ResponseInterface $response): void
    {
        $container = self::getContainer();
        $mockHandler = $container->get(MockHandler::class);
        if ($mockHandler instanceof MockHandler) {
            $mockHandler->append($response);
        }
    }
}
