<?php

namespace App\Tests\Functional\Command;

use App\Command\Option;
use App\Command\ServiceConfigurationSetCommand;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;
use App\Services\ServiceConfiguration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

class ServiceConfigurationSetCommandTest extends KernelTestCase
{
    private ServiceConfigurationSetCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(ServiceConfigurationSetCommand::class);
        \assert($command instanceof ServiceConfigurationSetCommand);
        $this->command = $command;
    }

    /**
     * @dataProvider runEmptyRequiredValueDataProvider
     *
     * @param array<mixed> $input
     */
    public function testRunEmptyRequiredValue(array $input, int $expectedReturnCode): void
    {
        $commandReturnCode = $this->command->run(new ArrayInput($input), new NullOutput());

        self::assertSame($expectedReturnCode, $commandReturnCode);
    }

    /**
     * @return array<mixed>
     */
    public function runEmptyRequiredValueDataProvider(): array
    {
        return [
            'empty service id' => [
                'input' => [],
                'expectedReturnCode' => ServiceConfigurationSetCommand::EXIT_CODE_EMPTY_SERVICE_ID,
            ],
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
     * @dataProvider runDataProvider
     *
     * @param array<mixed> $input
     */
    public function testRunSuccess(
        array $input,
        bool $setServiceConfigurationFixture,
        ServiceConfigurationModel $expectedConfiguration,
        int $expectedReturnCode
    ): void {
        $serviceConfiguration = \Mockery::mock(ServiceConfiguration::class);
        $serviceConfiguration
            ->shouldReceive('setServiceConfiguration')
            ->withArgs(function (ServiceConfigurationModel $configuration) use ($expectedConfiguration) {
                self::assertEquals($expectedConfiguration, $configuration);

                return true;
            })
            ->andReturn($setServiceConfigurationFixture)
        ;

        ObjectReflector::setProperty(
            $this->command,
            $this->command::class,
            'serviceConfiguration',
            $serviceConfiguration
        );

        $commandReturnCode = $this->command->run(new ArrayInput($input), new NullOutput());

        self::assertSame($expectedReturnCode, $commandReturnCode);
    }

    /**
     * @return array<mixed>
     */
    public function runDataProvider(): array
    {
        return [
            'not exists' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id_1',
                    '--' . ServiceConfigurationSetCommand::OPTION_HEALTH_CHECK_URL => '/health-check-1',
                    '--' . ServiceConfigurationSetCommand::OPTION_STATE_URL => '/state-1',
                ],
                'setServiceConfigurationFixture' => false,
                'expectedConfiguration' => new ServiceConfigurationModel(
                    'service_id_1',
                    '/health-check-1',
                    '/state-1'
                ),
                'expectedReturnCode' => Command::FAILURE,
            ],
            'exists' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id_2',
                    '--' . ServiceConfigurationSetCommand::OPTION_HEALTH_CHECK_URL => '/health-check-2',
                    '--' . ServiceConfigurationSetCommand::OPTION_STATE_URL => '/state-2',
                ],
                'setServiceConfigurationFixture' => true,
                'expectedConfiguration' => new ServiceConfigurationModel(
                    'service_id_2',
                    '/health-check-2',
                    '/state-2'
                ),
                'expectedReturnCode' => Command::SUCCESS,
            ],
        ];
    }
}
