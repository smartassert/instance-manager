<?php

namespace App\Tests\Integration;

use App\Command\InstanceCreateCommand;
use App\Command\InstanceDestroyCommand;
use App\Command\InstanceIsHealthyCommand;
use App\Command\InstanceListCommand;
use App\Command\IpAssignCommand;
use App\Command\IpCreateCommand;
use App\Command\IpGetCommand;
use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\Constraint\StringContains;
use PHPUnit\Framework\TestCase;

class EmptyServiceTokenEnvironmentVariableTest extends TestCase
{
    /**
     * @dataProvider commandForEmptyEnvironmentVariableDataProvider
     */
    public function testExecuteCommandWithEnvironmentVariableEmpty(string $command): void
    {
        $this->doTest('php bin/console ' . $command . ' --env=prod', true);
    }

    /**
     * @dataProvider commandForNonEmptyEnvironmentVariableDataProvider
     */
    public function testExecuteCommandWithEnvironmentVariableNotEmpty(string $command): void
    {
        $this->doTest('php bin/console ' . $command . ' --env=test', false);
    }

    /**
     * @return array<mixed>
     */
    public function commandForEmptyEnvironmentVariableDataProvider(): array
    {
        return [
            InstanceCreateCommand::NAME => [
                'command' => InstanceCreateCommand::NAME,
            ],
            InstanceIsHealthyCommand::NAME => [
                'command' => InstanceIsHealthyCommand::NAME,
            ],
            InstanceListCommand::NAME => [
                'command' => InstanceListCommand::NAME,
            ],
            InstanceDestroyCommand::NAME => [
                'command' => InstanceDestroyCommand::NAME,
            ],
            IpAssignCommand::NAME => [
                'command' => IpAssignCommand::NAME,
            ],
            IpCreateCommand::NAME => [
                'command' => IpCreateCommand::NAME,
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function commandForNonEmptyEnvironmentVariableDataProvider(): array
    {
        return [
            InstanceCreateCommand::NAME => [
                'command' => InstanceCreateCommand::NAME,
            ],
            InstanceIsHealthyCommand::NAME => [
                'command' => InstanceIsHealthyCommand::NAME,
            ],
            IpGetCommand::NAME => [
                'command' => IpGetCommand::NAME,
            ],
            InstanceListCommand::NAME => [
                'command' => InstanceListCommand::NAME,
            ],
            InstanceDestroyCommand::NAME => [
                'command' => InstanceDestroyCommand::NAME,
            ],
            IpAssignCommand::NAME => [
                'command' => IpAssignCommand::NAME,
            ],
            IpCreateCommand::NAME => [
                'command' => IpCreateCommand::NAME,
            ],
        ];
    }

    private function doTest(string $processCommand, bool $expectEnvironmentVariableErrorMessage): void
    {
        $output = (string) shell_exec("INSTANCE_COLLECTION_TAG=foo {$processCommand} 2>&1");
        $expectedMessage = 'Environment variable "SERVICE_TOKEN" is not allowed to be empty';

        $constraint = new StringContains($expectedMessage);
        if (false === $expectEnvironmentVariableErrorMessage) {
            $constraint = new LogicalNot(($constraint));
        }

        static::assertThat($output, $constraint);
    }
}
