<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\Option;
use App\Command\ServiceConfigurationSetCommand;
use App\Model\Service\UrlCollection;
use App\Services\UrlCollectionPersisterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

class ServiceConfigurationSetCommandTest extends KernelTestCase
{
    use MissingServiceIdTestTrait;

    private ServiceConfigurationSetCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(ServiceConfigurationSetCommand::class);
        \assert($command instanceof ServiceConfigurationSetCommand);
        $this->command = $command;
    }

    /**
     * @param array<mixed> $input
     */
    #[DataProvider('runEmptyRequiredValueDataProvider')]
    public function testRunEmptyRequiredValue(array $input, int $expectedReturnCode): void
    {
        $commandReturnCode = $this->command->run(new ArrayInput($input), new NullOutput());

        self::assertSame($expectedReturnCode, $commandReturnCode);
    }

    /**
     * @return array<mixed>
     */
    public static function runEmptyRequiredValueDataProvider(): array
    {
        return [
            'empty health check url' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                ],
                'expectedReturnCode' => ServiceConfigurationSetCommand::EXIT_CODE_EMPTY_HEALTH_CHECK_URL,
            ],
            'empty state url' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                    '--' . ServiceConfigurationSetCommand::OPTION_HEALTH_CHECK_URL => '/health-check',
                ],
                'expectedReturnCode' => ServiceConfigurationSetCommand::EXIT_CODE_EMPTY_STATE_URL,
            ],
        ];
    }

    /**
     * @param array<mixed> $input
     */
    #[DataProvider('runDataProvider')]
    public function testRunSuccess(
        array $input,
        bool $setServiceConfigurationFixture,
        string $expectedServiceId,
        string $expectedHealthCheckUrl,
        string $expectedStateUrl,
        int $expectedReturnCode
    ): void {
        $urlCollectionPersister = \Mockery::mock(UrlCollectionPersisterInterface::class);
        $urlCollectionPersister
            ->shouldReceive('persist')
            ->withArgs(function (
                string $serviceId,
                UrlCollection $urlCollection
            ) use (
                $expectedServiceId,
                $expectedHealthCheckUrl,
                $expectedStateUrl
            ) {
                self::assertSame($expectedServiceId, $serviceId);
                self::assertEquals(
                    new UrlCollection($expectedHealthCheckUrl, $expectedStateUrl),
                    $urlCollection
                );

                return true;
            })
            ->andReturn($setServiceConfigurationFixture)
        ;

        ObjectReflector::setProperty(
            $this->command,
            $this->command::class,
            'urlCollectionPersister',
            $urlCollectionPersister
        );

        $commandReturnCode = $this->command->run(new ArrayInput($input), new NullOutput());

        self::assertSame($expectedReturnCode, $commandReturnCode);
    }

    /**
     * @return array<mixed>
     */
    public static function runDataProvider(): array
    {
        return [
            'not exists' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id_1',
                    '--' . ServiceConfigurationSetCommand::OPTION_HEALTH_CHECK_URL => '/health-check-1',
                    '--' . ServiceConfigurationSetCommand::OPTION_STATE_URL => '/state-1',
                ],
                'setServiceConfigurationFixture' => false,
                'expectedServiceId' => 'service_id_1',
                'expectedHealthCheckUrl' => '/health-check-1',
                'expectedStateUrl' => '/state-1',
                'expectedReturnCode' => Command::FAILURE,
            ],
            'exists' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id_2',
                    '--' . ServiceConfigurationSetCommand::OPTION_HEALTH_CHECK_URL => '/health-check-2',
                    '--' . ServiceConfigurationSetCommand::OPTION_STATE_URL => '/state-2',
                ],
                'setServiceConfigurationFixture' => true,
                'expectedServiceId' => 'service_id_2',
                'expectedHealthCheckUrl' => '/health-check-2',
                'expectedStateUrl' => '/state-2',
                'expectedReturnCode' => Command::SUCCESS,
            ],
        ];
    }
}
