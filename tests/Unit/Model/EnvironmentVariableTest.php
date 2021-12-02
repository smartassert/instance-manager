<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\EnvironmentVariable;
use PHPUnit\Framework\TestCase;

class EnvironmentVariableTest extends TestCase
{
    /**
     * @dataProvider toStringDataProvider
     */
    public function testToString(EnvironmentVariable $environmentVariable, string $expected): void
    {
        self::assertSame($expected, (string) $environmentVariable);
    }

    /**
     * @return array<mixed>
     */
    public function toStringDataProvider(): array
    {
        $publicKeyValue = <<<'END'
            -----BEGIN PUBLIC KEY-----
            MIIBITANBgkqhkiG9w0BAQEFAAOCAQ4AMIIBCQKCAQBCT64wYsQ8qPbgnhnS+6TJ
            CuLonpGwbPiPEq11y5SeQGRm/O4nqH8ZQg6O6dvqwrQA3A9KDz0M91Z3KO3/2WbN
            ndGvF1LU23krIAYUdJP384iWkdz6qGkmPY8nwKrwhN0E4GFPigHAk3l1NkHHVDuk
            Ad/5WpMSgOQ4gcdvmmC/fFgRb09QdNm/tpgTt59IVWA5xnmeGMrqrZjK9QHQr7/e
            r9KCwNsThZ0hUAUvt5GJB5J2tYibvgjpfmOiomrVRP1JlUWvJoegds7GomsRwk7f
            mN0Kep9ZPydi/VV4bjo6xM/Lg0kvyu8ZBhdekMGe+u0esPZef05xWnDVm5DqOhxV
            AgMBAAE=
            -----END PUBLIC KEY-----
            END;

        return [
            'empty key, empty value' => [
                'environmentVariable' => new EnvironmentVariable('', ''),
                'expected' => '',
            ],
            'empty key, has value' => [
                'environmentVariable' => new EnvironmentVariable('', 'value'),
                'expected' => '',
            ],
            'has key, empty value' => [
                'environmentVariable' => new EnvironmentVariable('key', ''),
                'expected' => 'key=""',
            ],
            'has key, has single-line value' => [
                'environmentVariable' => new EnvironmentVariable('key', 'value'),
                'expected' => 'key="value"',
            ],
            'has key, has single-line value containing quotes' => [
                'environmentVariable' => new EnvironmentVariable('key', 'va"l"ue'),
                'expected' => 'key="va\"l\"ue"',
            ],
            'has key, has multi-line value (2048-bit public key)' => [
                'environmentVariable' => new EnvironmentVariable('key', $publicKeyValue),
                'expected' => 'key="' . $publicKeyValue . '"',
            ],
        ];
    }
}
