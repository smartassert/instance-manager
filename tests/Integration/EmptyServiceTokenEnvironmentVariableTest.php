<?php

namespace App\Tests\Integration;

use App\Command\InstanceCreateCommand;
use App\Command\InstanceDestroyCommand;
use App\Command\InstanceIsHealthyCommand;
use App\Command\InstanceListCommand;
use App\Command\IpAssignCommand;
use App\Command\IpCreateCommand;
use App\Command\IpGetCommand;

class EmptyServiceTokenEnvironmentVariableTest extends AbstractEmptyEnvironmentVariableTest
{
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

    protected function getDefinedEnvironmentVariables(): array
    {
        return [
            'INSTANCE_COLLECTION_TAG' => 'non-empty value',
        ];
    }

    protected function getExpectedEnvironmentVariable(): string
    {
        return 'SERVICE_TOKEN';
    }
}
