<?php

namespace App\Tests\Integration;

use App\Command\InstanceCreateCommand;
use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\Constraint\StringContains;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class EmptyInstanceTagCollectionEnvironmentVariableTest extends TestCase
{
    /**
     * @dataProvider commandDataProvider
     */
    public function testExecuteCommandWithInstanceTagCollectionEmpty(string $command): void
    {
        $this->doTest('php bin/console ' . $command . ' --env=prod', true);
    }

    /**
     * @dataProvider commandDataProvider
     */
    public function testExecuteCommandWithInstanceTagCollectionNotEmpty(string $command): void
    {
        $this->doTest('php bin/console ' . $command . ' --env=test', false);
    }

    /**
     * @return array<mixed>
     */
    public function commandDataProvider(): array
    {
        return [
            InstanceCreateCommand::NAME => [
                'command' => InstanceCreateCommand::NAME,
            ],
        ];
    }

    private function doTest(string $processCommand, bool $expectEnvironmentVariableErrorMessage): void
    {
        $process = Process::fromShellCommandline($processCommand);
        $process->run();

        $output = $process->getErrorOutput();
        self::assertNotEmpty($output);

        $expectedMessage = 'Environment variable "INSTANCE_COLLECTION_TAG" is not allowed to be empty';

        $constraint = new StringContains($expectedMessage);
        if (false === $expectEnvironmentVariableErrorMessage) {
            $constraint = new LogicalNot(($constraint));
        }

        static::assertThat($output, $constraint);
    }
}
