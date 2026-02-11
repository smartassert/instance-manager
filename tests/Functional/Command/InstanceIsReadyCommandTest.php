<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InstanceIsReadyCommand;
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
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
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
class InstanceIsReadyCommandTest extends KernelTestCase
{
    use MissingServiceIdTestTrait;
    use MockeryPHPUnitIntegration;

    private InstanceIsReadyCommand $command;
    private MockHandler $mockHandler;
    private HttpResponseFactory $httpResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(InstanceIsReadyCommand::class);
        \assert($command instanceof InstanceIsReadyCommand);
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

        $this->expectExceptionObject(
            new ServiceConfigurationMissingException($serviceId, Filename::URL_COLLECTION->value)
        );

        $this->command->run(new ArrayInput(['--' . Option::OPTION_SERVICE_ID => $serviceId]), new NullOutput());
    }

    public function testRunWithoutStateUrlThrowsException(): void
    {
        $serviceId = 'service_id';

        $exception = new ConfigurationFileValueMissingException(
            Filename::URL_COLLECTION->value,
            'state_url',
            'service_id'
        );

        $urlLoader = \Mockery::mock(UrlLoaderInterface::class);
        $urlLoader
            ->shouldReceive('load')
            ->with($serviceId, UrlKey::STATE)
            ->andThrow($exception)
        ;

        ObjectReflector::setProperty($this->command, $this->command::class, 'urlLoader', $urlLoader);

        $this->expectExceptionObject($exception);

        $this->command->run(new ArrayInput(['--' . Option::OPTION_SERVICE_ID => $serviceId]), new NullOutput());
    }

    public function testRunInvalidApiToken(): void
    {
        $serviceId = 'service_id';

        $urlLoader = \Mockery::mock(UrlLoaderInterface::class);
        $urlLoader
            ->shouldReceive('load')
            ->with($serviceId, UrlKey::STATE)
            ->andReturn('https://{{ host }}/state')
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
     * @param array<mixed>                            $input
     * @param array<int, HttpResponseData|\Throwable> $httpResponseDataCollection
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

        foreach ($httpResponseDataCollection as $fixture) {
            if (is_array($fixture)) {
                $fixture = $this->httpResponseFactory->createFromArray($fixture);
            }

            $this->mockHandler->append($fixture);
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
        $dropletHttpResponseData = HttpResponseDataFactory::createJsonResponseData([
            'droplet' => [
                'id' => $instanceId,
            ],
        ]);

        $validUrlLoader = (function (string $serviceId) {
            $urlLoader = \Mockery::mock(UrlLoaderInterface::class);
            $urlLoader
                ->shouldReceive('load')
                ->with($serviceId, UrlKey::STATE)
                ->andReturn('https://{{ host }}/state')
            ;

            return $urlLoader;
        })($serviceId);

        return [
            'no explicit readiness state' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => 'ready',
            ],
            'ready=false, retry-limit=1' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                    '--retry-limit' => 1,
                    '--retry-delay' => 0,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => false,
                    ]),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'not-ready',
            ],
            'ready=false, ready=false, retry-limit=2' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => false,
                    ]),
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => false,
                    ]),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'not-ready' . "\n" . 'not-ready',
            ],
            'ready=false, exception, retry-limit=2' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => false,
                    ]),
                    new \RuntimeException('exception message content'),
                ],
                'expectedReturnCode' => Command::FAILURE,
                'expectedOutput' => 'not-ready' . "\n" . 'exception message content',
            ],
            'ready=true' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => true,
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => 'ready',
            ],
            'ready=false, ready=true, retry-limit=2' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                    '--retry-limit' => 2,
                    '--retry-delay' => 0,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => false,
                    ]),
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => true,
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => 'not-ready' . "\n" . 'ready',
            ],
            'ready=<non-boolean>' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => $serviceId,
                    '--id' => (string) $instanceId,
                ],
                'urlLoader' => $validUrlLoader,
                'httpResponseDataCollection' => [
                    $dropletHttpResponseData,
                    HttpResponseDataFactory::createJsonResponseData([
                        'ready' => 'non-boolean value',
                    ]),
                ],
                'expectedReturnCode' => Command::SUCCESS,
                'expectedOutput' => 'ready',
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
            ->with($serviceId, UrlKey::STATE)
            ->andReturn('https://{{ host }}/state')
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
            ->with($serviceId, UrlKey::STATE)
            ->andReturn('https://{{ host }}/state')
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
