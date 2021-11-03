<?php

namespace App\Tests\Integration;

use App\Command\IpAssignCommand;
use App\Command\IpCreateCommand;
use App\Command\IpGetCommand;

class EmptyInstanceTagCollectionEnvironmentVariableTest extends AbstractEmptyEnvironmentVariableTest
{
    public function commandForEmptyEnvironmentVariableDataProvider(): array
    {
        return $this->commandDataProvider();
    }

    public function commandForNonEmptyEnvironmentVariableDataProvider(): array
    {
        return $this->commandDataProvider();
    }

    /**
     * @return array<mixed>
     */
    public function commandDataProvider(): array
    {
        return [
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
        return [];
    }

    protected function getExpectedEnvironmentVariable(): string
    {
        return 'INSTANCE_COLLECTION_TAG';
    }
}
