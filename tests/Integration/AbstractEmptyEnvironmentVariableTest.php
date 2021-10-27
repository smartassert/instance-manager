<?php

namespace App\Tests\Integration;

use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\Constraint\StringContains;
use PHPUnit\Framework\TestCase;

abstract class AbstractEmptyEnvironmentVariableTest extends TestCase
{
    /**
     * @dataProvider commandForEmptyEnvironmentVariableDataProvider
     */
    public function testExecuteCommandWithEnvironmentVariableEmpty(string $command): void
    {
        $this->doTest(
            'php bin/console ' . $command . ' --env=prod',
            $this->getDefinedEnvironmentVariables(),
            $this->getExpectedEnvironmentVariable(),
            true
        );
    }

    /**
     * @dataProvider commandForNonEmptyEnvironmentVariableDataProvider
     */
    public function testExecuteCommandWithEnvironmentVariableNotEmpty(string $command): void
    {
        $this->doTest(
            'php bin/console ' . $command . ' --env=test',
            $this->getDefinedEnvironmentVariables(),
            $this->getExpectedEnvironmentVariable(),
            false
        );
    }

    /**
     * @return array<mixed>
     */
    abstract public function commandForEmptyEnvironmentVariableDataProvider(): array;

    /**
     * @return array<mixed>
     */
    abstract public function commandForNonEmptyEnvironmentVariableDataProvider(): array;

    /**
     * @return array<string, string>
     */
    abstract protected function getDefinedEnvironmentVariables(): array;

    abstract protected function getExpectedEnvironmentVariable(): string;

    /**
     * @param array<string, string> $environmentVariables
     */
    private function doTest(
        string $processCommand,
        array $environmentVariables,
        string $expectedEnvironmentVariable,
        bool $expectEnvironmentVariableErrorMessage
    ): void {
        $environmentVariablesComponent = '';
        foreach ($environmentVariables as $name => $value) {
            $environmentVariablesComponent .= $name . '="' . $value . '" ';
        }

        $command = sprintf(
            '%s %s 2>&1',
            $environmentVariablesComponent,
            $processCommand
        );

        $output = (string) shell_exec($command);

        $expectedMessage = 'Environment variable "' . $expectedEnvironmentVariable . '" is not allowed to be empty';

        $constraint = new StringContains($expectedMessage);
        if (false === $expectEnvironmentVariableErrorMessage) {
            $constraint = new LogicalNot(($constraint));
        }

        static::assertThat($output, $constraint);
    }
}
