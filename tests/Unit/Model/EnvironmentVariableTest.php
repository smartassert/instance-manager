<?php

namespace App\Tests\Unit\Model;

use App\Model\EnvironmentVariable;
use PHPUnit\Framework\TestCase;

class EnvironmentVariableTest extends TestCase
{
    public function testToString(): void
    {
        self::assertSame(
            '',
            (string) new EnvironmentVariable('', '')
        );

        self::assertSame(
            'key=""',
            (string) new EnvironmentVariable('key', '')
        );

        self::assertSame(
            'key="value"',
            (string) new EnvironmentVariable('key', 'value')
        );
    }
}
