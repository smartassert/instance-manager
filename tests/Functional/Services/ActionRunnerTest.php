<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\ActionHandler\ActionHandler;
use App\Exception\ActionTimeoutException;
use App\Model\Instance;
use App\Services\ActionRunner;
use App\Services\FloatingIpManager;
use App\Services\InstanceRepository;
use App\Tests\Services\DropletDataFactory;
use App\Tests\Services\HttpResponseFactory;
use GuzzleHttp\Handler\MockHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ActionRunnerTest extends KernelTestCase
{
    private const MICROSECONDS_PER_SECOND = 1000000;

    private ActionRunner $actionRunner;

    protected function setUp(): void
    {
        parent::setUp();

        $actionRunner = self::getContainer()->get(ActionRunner::class);
        \assert($actionRunner instanceof ActionRunner);
        $this->actionRunner = $actionRunner;
    }

    /**
     * @dataProvider runSuccessSimpleDataProvider
     */
    public function testRunSuccessSimple(
        ActionHandler $decider,
        int $maximumDurationInMicroseconds,
        int $retryPeriodInMicroseconds
    ): void {
        $this->actionRunner->run($decider, $maximumDurationInMicroseconds, $retryPeriodInMicroseconds);
        self::expectNotToPerformAssertions();
    }

    /**
     * @return array<mixed>
     */
    public function runSuccessSimpleDataProvider(): array
    {
        $delayedSuccessCount = 0;
        $delayedSuccessLimit = 3;

        return [
            'immediate success' => [
                'decider' => new ActionHandler(
                    function () {
                        return true;
                    },
                    function () {
                    }
                ),
                'maximumDurationInMicroSeconds' => 1000,
                'retryPeriodInMicroseconds' => 10,
            ],
            'delayed success, basic' => [
                'decider' => new ActionHandler(
                    function () use ($delayedSuccessLimit, &$delayedSuccessCount) {
                        if ($delayedSuccessCount < $delayedSuccessLimit) {
                            ++$delayedSuccessCount;

                            return false;
                        }

                        return true;
                    },
                    function () {
                    }
                ),
                'maximumDurationInMicroSeconds' => 1000,
                'retryPeriodInMicroseconds' => 10,
            ],
        ];
    }

    public function testRunSuccessComplex(): void
    {
        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);

        $httpResponseFactory = self::getContainer()->get(HttpResponseFactory::class);
        \assert($httpResponseFactory instanceof HttpResponseFactory);

        $expectedIp = '127.0.0.2';

        $mockHandler->append(...[
            'find current instance' => $httpResponseFactory->createFromArray([
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json; charset=utf-8',
                ],
                HttpResponseFactory::KEY_BODY => (string) json_encode([
                    'droplets' => [
                        [
                            'id' => 123,
                        ],
                    ],
                ])
            ]),
            'get instance, does not have expected IP' => $httpResponseFactory->createFromArray([
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json; charset=utf-8',
                ],
                HttpResponseFactory::KEY_BODY => (string) json_encode([
                    'droplet' => DropletDataFactory::createWithIps(123, ['127.0.0.1']),
                ])
            ]),
            'get instance, has expected IP' => $httpResponseFactory->createFromArray([
                HttpResponseFactory::KEY_STATUS_CODE => 200,
                HttpResponseFactory::KEY_HEADERS => [
                    'content-type' => 'application/json; charset=utf-8',
                ],
                HttpResponseFactory::KEY_BODY => (string) json_encode([
                    'droplet' => DropletDataFactory::createWithIps(123, ['127.0.0.1', $expectedIp]),
                ])
            ]),
        ]);

        $floatingIpManager = self::getContainer()->get(FloatingIpManager::class);
        \assert($floatingIpManager instanceof FloatingIpManager);

        $instanceRepository = self::getContainer()->get(InstanceRepository::class);
        \assert($instanceRepository instanceof InstanceRepository);

        $instance = $instanceRepository->findCurrent('service_id', 123456);
        \assert($instance instanceof Instance);

        $decider = new ActionHandler(
            function (mixed $actionResult) use ($expectedIp) {
                return $actionResult instanceof Instance && $actionResult->hasIp($expectedIp);
            },
            function () use ($instance, $instanceRepository) {
                return $instanceRepository->find($instance->getId());
            }
        );

        $maximumDurationInMicroseconds = (int) (self::MICROSECONDS_PER_SECOND * 10);
        $retryPeriodInMicroseconds = (int) (self::MICROSECONDS_PER_SECOND * 0.1);

        $this->actionRunner->run($decider, $maximumDurationInMicroseconds, $retryPeriodInMicroseconds);

        self::assertCount(0, $mockHandler);
    }

    public function testRunFailure(): void
    {
        $decider = new ActionHandler(
            function () {
                return false;
            },
            function () {
            }
        );

        self::expectException(ActionTimeoutException::class);

        $this->actionRunner->run($decider, 10, 1);
    }
}
