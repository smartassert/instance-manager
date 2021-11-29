<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\Option;
use App\Command\ServiceConfigurationExistsCommand;
use App\Services\ServiceConfiguration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

class ServiceConfigurationExistsCommandTest extends KernelTestCase
{
    private ServiceConfigurationExistsCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(ServiceConfigurationExistsCommand::class);
        \assert($command instanceof ServiceConfigurationExistsCommand);
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
                'expectedReturnCode' => ServiceConfigurationExistsCommand::EXIT_CODE_EMPTY_SERVICE_ID,
            ],
        ];
    }

    /**
     * @dataProvider runDataProvider
     *
     * @param array<mixed> $input
     */
    public function testRunSuccess(array $input, bool $existsFixture, int $expectedReturnCode): void
    {
        $serviceConfiguration = \Mockery::mock(ServiceConfiguration::class);
        $serviceConfiguration
            ->shouldReceive('exists')
            ->with($input['--' . Option::OPTION_SERVICE_ID])
            ->andReturn($existsFixture)
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
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                ],
                'existsFixture' => false,
                'expectedReturnCode' => Command::FAILURE,
            ],
            'exists' => [
                'input' => [
                    '--' . Option::OPTION_SERVICE_ID => 'service_id',
                ],
                'existsFixture' => true,
                'expectedReturnCode' => Command::SUCCESS,
            ],
        ];
    }
}
