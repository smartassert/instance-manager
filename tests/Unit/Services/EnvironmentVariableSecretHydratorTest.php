<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableCollection;
use App\Model\Secret;
use App\Model\SecretCollection;
use App\Services\EnvironmentVariableSecretHydrator;
use PHPUnit\Framework\TestCase;

class EnvironmentVariableSecretHydratorTest extends TestCase
{
    /**
     * @dataProvider hydrateDataProvider
     */
    public function testHydrateCollection(
        EnvironmentVariableCollection $environmentVariables,
        SecretCollection $secrets,
        EnvironmentVariableCollection $expected
    ): void {
        $hydrator = new EnvironmentVariableSecretHydrator();

        self::assertEquals(
            $expected,
            $hydrator->hydrateCollection($environmentVariables, $secrets)
        );
    }

    /**
     * @return array<mixed>
     */
    public function hydrateDataProvider(): array
    {
        return [
            'empty collection, no secrets' => [
                'environmentVariables' => new EnvironmentVariableCollection(),
                'secrets' => new SecretCollection(),
                'expected' => new EnvironmentVariableCollection(),
            ],
            'single non-secret value, no secrets' => [
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('env-var-name', 'value'),
                ]),
                'secrets' => new SecretCollection(),
                'expected' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('env-var-name', 'value'),
                ]),
            ],
            'single secret value, no secrets' => [
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('env-var-name', '{{ secrets.SECRET_NAME }}'),
                ]),
                'secrets' => new SecretCollection(),
                'expected' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('env-var-name', '{{ secrets.SECRET_NAME }}'),
                ]),
            ],
            'single secret value, no matching secrets' => [
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('env-var-name', '{{ secrets.SECRET_NAME }}'),
                ]),
                'secrets' => new SecretCollection([
                    new Secret('KEY1', 'value1'),
                ]),
                'expected' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('env-var-name', '{{ secrets.SECRET_NAME }}'),
                ]),
            ],
            'single secret value, has matching secrets' => [
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('env-var-name', '{{ secrets.SECRET_NAME }}'),
                ]),
                'secrets' => new SecretCollection([
                    new Secret('SECRET_NAME', 'secret content'),
                ]),
                'expected' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('env-var-name', 'secret content'),
                ]),
            ],
            'multiple secret values, has matching secrets' => [
                'environmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('env-var-name-1', '{{ secrets.SECRET_1 }}'),
                    new EnvironmentVariable('env-var-name-2', '{{ secrets.SECRET_1 }}'),
                    new EnvironmentVariable('env-var-name-3', '{{ secrets.SECRET_2 }}'),
                    new EnvironmentVariable('env-var-name-4', '{{ secrets.SECRET_3 }}'),
                ]),
                'secrets' => new SecretCollection([
                    new Secret('SECRET_1', 'secret content 1'),
                    new Secret('SECRET_2', 'secret content 2'),
                    new Secret('SECRET_3', 'secret content 3'),
                ]),
                'expected' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('env-var-name-1', 'secret content 1'),
                    new EnvironmentVariable('env-var-name-2', 'secret content 1'),
                    new EnvironmentVariable('env-var-name-3', 'secret content 2'),
                    new EnvironmentVariable('env-var-name-4', 'secret content 3'),
                ]),
            ],
        ];
    }
}
