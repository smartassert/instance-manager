<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class CommandsAreRegisteredTest extends TestCase
{
    public function testCommandsAreListedWithoutErrorOutput(): void
    {
        $listCommandsProcess = Process::fromShellCommandline('php bin/console list app --raw');
        $listCommandsProcess->run();

        self::assertSame(0, $listCommandsProcess->getExitCode());
        self::assertSame('', $listCommandsProcess->getErrorOutput());
    }

    public function testAllCommandsAreListed(): void
    {
        $listCommandsProcess = Process::fromShellCommandline('php bin/console list app --raw');
        $listCommandsProcess->run();

        $commandListCommandNames = $this->getCommandListCommandNames($listCommandsProcess->getOutput());
        sort($commandListCommandNames);

        $commandNames = $this->findCommandNames();
        sort($commandNames);

        self::assertSame($commandNames, $commandListCommandNames);
    }

    /**
     * @return string[]
     */
    private function findCommandNames(): array
    {
        $finder = new Finder();
        $finder
            ->files()
            ->name('/^(?!Abstract).*Command\.php/')
            ->in((string) realpath(__DIR__ . '/../../src/Command'))
        ;

        $names = [];

        foreach ($finder as $item) {
            $name = $this->getCommandFileCommandName($item->getFilename());

            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function getCommandFileCommandName(string $commandFilename): ?string
    {
        $className = str_replace('.php', '', $commandFilename);

        /** @var class-string $fullyQualifiedClassName */
        $fullyQualifiedClassName = 'App\Command\\' . $className;

        $reflectionClass = new \ReflectionClass($fullyQualifiedClassName);
        $name = $reflectionClass->getConstant('NAME');

        return is_string($name) ? $name : null;
    }

    /**
     * @return string[]
     */
    private function getCommandListCommandNames(string $commandList): array
    {
        $names = [];

        $lines = explode("\n", trim($commandList));

        foreach ($lines as $line) {
            $lineParts = explode(' ', $line, 2);
            $names[] = $lineParts[0];
        }

        return $names;
    }
}
