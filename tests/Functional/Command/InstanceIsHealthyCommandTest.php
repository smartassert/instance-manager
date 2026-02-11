<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceIsHealthyCommand;
use App\Command\Option;
use App\Enum\Filename;
use App\Enum\UrlKey;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\InstanceNotFoundException;
use App\Exception\RequiredOptionMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Services\UrlLoaderInterface;
use App\Tests\Services\HttpResponseDataFactory;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

/**
 * @phpstan-import-type HttpResponseData from HttpResponseFactory
 */
class InstanceIsHealthyCommandTest extends KernelTestCase
{
    use MissingServiceIdTestTrait;

    private InstanceIsHealthyCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(InstanceIsHealthyCommand::class);
        \assert($command instanceof InstanceIsHealthyCommand);
        $this->command = $command;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);
        $this->httpResponseFactory = $httpResponseFactory;
    }

    public function testRunWithoutServiceConfigurationFileThrowsException(): void
    {
        $serviceId = 'service_id';
        $instanceId = '123';

        $this->expectExceptionObject(
            new ServiceConfigurationMissingException($serviceId, Filename::URL_COLLECTION->value)
        );

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => $serviceId,
                '--' . Option::OPTION_ID => $instanceId,
            ]),
            new NullOutput()
        );
    }

    public function testRunWithoutHealthCheckUrlThrowsException(): void
    {
        $serviceId = md5((string) rand());
        $instanceId = rand(100, 900);

        $exception = new ConfigurationFileValueMissingException(
            Filename::URL_COLLECTION->value,
            'health_check_url',
            $serviceId
        );

        $urlLoader = \Mockery::mock(UrlLoaderInterface::class);
        $urlLoader
            ->shouldReceive('load')
            ->with($serviceId, UrlKey::HEALTH_CHECK)
            ->andThrow($exception)
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'urlLoader', $urlLoader);

        $this->expectExceptionObject($exception);

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => $serviceId,
                '--' . Option::OPTION_ID => $instanceId,
            ]),
            new NullOutput()
        );
    }

    public function testRunInvalidApiToken(): void
    {
        $serviceId = md5((string) rand());

        $urlLoader = \Mockery::mock(UrlLoaderInterface::class);
        $urlLoader
            ->shouldReceive('load')
            ->with($serviceId, UrlKey::HEALTH_CHECK)
            ->andReturn('/health-check')
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'urlLoader', $urlLoader);

        $this->mockHandler->append(new Response(401));

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unauthorized');
        self::expectExceptionCode(401);

        $this->command->run(
            new ArrayInput([
                '--' . Option::OPTION_SERVICE_ID => $serviceId,
                '--' . Option::OPTION_ID => '123',
            ]),
            new BufferedOutput()
        );
    }

    /**
     * @param array<mixed>                 $input
     * @param array<int, HttpResponseData> $httpResponseDataCollection
     */
    #[DataProvider('runSuccessDataProvider')]
    public function testRunSuccess(
        array $input,
        UrlLoaderInterface $urlLoader,
        array $httpResponseDataCollection,
        int $expectedReturnCode,
        string $expectedOutput
    ): void {
        ObjectReflector::setProperty($this->command, $this->command::class, 'urlLoader', $urlLoader);

        foreach ($httpResponseDataCollection as $httpResponseData) {
            $this->mockHandler->append(
                $this->httpResponseFactory->createFromArray($httpResponseData)
            );
        }

        $output = new BufferedOutput();

        $commandReturnCode = $this->command->run(new ArrayInput($input), $output);

        self::assertSame($expectedReturnCode, $commandReturnCode);
        self::assertSame($expectedOutput, $output->fetch());
    }

    /**
     * @return array<mixed>
     */
    public static function runSuccessDataProvider(): array
    {
        $serviceId = 'service_id';
        $instanceId = 123;

        $validUrlLoader = (function (string $serviceId) {
            $urlLoader = \Mockery::mock(UrlLoaderInterface::class);
            $urlLoader
                ->shouldReceive('load')
                ->with($serviceId, UrlKey::HEALTH_CHECK)
                ->andReturn('https://{{ host }}/health-check')
            ;

            return $urlLoader;
        })($serviceId);

        $dropletResponseData = HttpResponseDataFactory::createJsonResponseData([
            'droplet' => [
                'id' => $instanceId,
            ],
        ]);

        return [
            'no health check url' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => (string) $instanceId,
                ],
                'urlLoader' => (function (string $serviceId) {
                    $urlLoader = \Mockery::mock(UrlLoaderInterface::class);
                    $urlLoader
                        ->shouldReceive('load')
                        ->with($serviceId, UrlKey::HEALTH_CHECK)
                        ->andReturn('')
                    ;

                    return $urlLoader;
                })($serviceId),
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => '',
            ],
            'no health data' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => (string) $instanceId,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                    HttpResponseDataFactory::createJsonResponseData([]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => '[]',
            ],
            'not healthy, retry-limit=1' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => (string) $instanceId,
                    '--retry-limit' => 1,
                    '--retry-delay' => 0,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                    HttpResponseDataFactory::createJsonResponseData(
                        [
                            'service1' => 'unavailable',
                            'service2' => 'available',
                            'service3' => 'available',
                        ],
                        503
                    ),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => (string) json_encode([
                    'service1' => 'unavailable',
                    'service2' => 'available',
                    'service3' => 'available',
                ]),
            ],
            'not healthy, not-healthy, retry-limit=2' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => (string) $instanceId,
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                    HttpResponseDataFactory::createJsonResponseData(
                        [
                            'service1' => 'unavailable',
                            'service2' => 'available',
                        ],
                        503
                    ),
                    HttpResponseDataFactory::createJsonResponseData(
                        [
                            'service1' => 'unavailable',
                            'service2' => 'available',
                        ],
                        503
                    ),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => json_encode(['service1' => 'unavailable', 'service2' => 'available']) . "\n"
                    . json_encode(['service1' => 'unavailable', 'service2' => 'available']),
            ],
            'not healthy, healthy, retry-limit=2' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => (string) $instanceId,
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                    HttpResponseDataFactory::createJsonResponseData(
                        [
                            'service1' => 'unavailable',
                            'service2' => 'available',
                        ],
                        503
                    ),
                    HttpResponseDataFactory::createJsonResponseData([
                        'service1' => 'available',
                        'service2' => 'available',
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => json_encode(['service1' => 'unavailable', 'service2' => 'available']) . "\n"
                    . json_encode(['service1' => 'available', 'service2' => 'available']),
            ],
            'healthy' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => (string) $instanceId,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'service1' => 'available',
                        'service2' => 'available',
                        'service3' => 'available',
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => (string) json_encode([
                    'service1' => 'available',
                    'service2' => 'available',
                    'service3' => 'available',
                ]),
            ],
        ];
    }

    /**
     * @param array<mixed> $input
     */
    #[DataProvider('runWithoutInstanceIdThrowsExceptionDataProvider')]
    public function testRunWithoutInstanceIdThrowsException(string $serviceId, array $input): void
    {
        $urlLoader = \Mockery::mock(UrlLoaderInterface::class);
        $urlLoader
            ->shouldReceive('load')
            ->with($serviceId, UrlKey::HEALTH_CHECK)
            ->andReturn('/health-check')
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'urlLoader', $urlLoader);

        $this->expectExceptionObject(new RequiredOptionMissingException(Option::OPTION_ID));

        $this->command->run(new ArrayInput($input), new NullOutput());
    }

    /**
     * @return array<mixed>
     */
    public static function runWithoutInstanceIdThrowsExceptionDataProvider(): array
    {
        $serviceId = md5((string) rand());

        return [
            'missing' => [
                'serviceId' => $serviceId,
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                ],
            ],
            'not numeric' => [
                'serviceId' => $serviceId,
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--' . Option::OPTION_ID => 'not-numeric',
                ],
            ],
        ];
    }

    public function testRunWithNonExistentInstanceThrowsException(): void
    {
        $serviceId = 'service_id';

        $urlLoader = \Mockery::mock(UrlLoaderInterface::class);
        $urlLoader
            ->shouldReceive('load')
            ->with($serviceId, UrlKey::HEALTH_CHECK)
            ->andReturn('/health-check')
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'urlLoader', $urlLoader);

        $this->mockHandler->append(new Response(404));

        $this->expectExceptionObject(new InstanceNotFoundException(123));

        $input = [
            '--' . Option::OPTION_SERVICE_ID => $serviceId,
            '--' . Option::OPTION_ID => '123',
        ];

        $this->command->run(new ArrayInput($input), new NullOutput());
    }
}
