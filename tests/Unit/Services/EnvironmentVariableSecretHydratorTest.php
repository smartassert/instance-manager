<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Model\EnvironmentVariable;
use App\Model\KeyValue;
use App\Services\EnvironmentVariableSecretHydrator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class EnvironmentVariableSecretHydratorTest extends TestCase
{
    /**
     * @dataProvider hydrateDataProvider
     *
     * @param Collection<int, EnvironmentVariable> $environmentVariables
     * @param Collection<int, KeyValue>            $secrets
     * @param Collection<int, EnvironmentVariable> $expected
     */
    public function testHydrateCollection(
        Collection $environmentVariables,
        Collection $secrets,
        Collection $expected
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
                'environmentVariables' => new ArrayCollection(),
                'secrets' => new ArrayCollection(),
                'expected' => new ArrayCollection(),
            ],
            'single non-secret value, no secrets' => [
                'environmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('env-var-name', 'value'),
                ]),
                'secrets' => new ArrayCollection(),
                'expected' => new ArrayCollection([
                    new EnvironmentVariable('env-var-name', 'value'),
                ]),
            ],
            'single secret value, no secrets' => [
                'environmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('env-var-name', '{{ secrets.SECRET_NAME }}'),
                ]),
                'secrets' => new ArrayCollection(),
                'expected' => new ArrayCollection([
                    new EnvironmentVariable('env-var-name', '{{ secrets.SECRET_NAME }}'),
                ]),
            ],
            'single secret value, no matching secrets' => [
                'environmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('env-var-name', '{{ secrets.SECRET_NAME }}'),
                ]),
                'secrets' => new ArrayCollection([
                    new KeyValue('KEY1', 'value1'),
                ]),
                'expected' => new ArrayCollection([
                    new EnvironmentVariable('env-var-name', '{{ secrets.SECRET_NAME }}'),
                ]),
            ],
            'single secret value, has matching secrets' => [
                'environmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('env-var-name', '{{ secrets.SECRET_NAME }}'),
                ]),
                'secrets' => new ArrayCollection([
                    new KeyValue('SECRET_NAME', 'secret content'),
                ]),
                'expected' => new ArrayCollection([
                    new EnvironmentVariable('env-var-name', 'secret content'),
                ]),
            ],
            'multiple secret values, has matching secrets' => [
                'environmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('env-var-name-1', '{{ secrets.SECRET_1 }}'),
                    new EnvironmentVariable('env-var-name-2', '{{ secrets.SECRET_1 }}'),
                    new EnvironmentVariable('env-var-name-3', '{{ secrets.SECRET_2 }}'),
                    new EnvironmentVariable('env-var-name-4', '{{ secrets.SECRET_3 }}'),
                ]),
                'secrets' => new ArrayCollection([
                    new KeyValue('SECRET_1', 'secret content 1'),
                    new KeyValue('SECRET_2', 'secret content 2'),
                    new KeyValue('SECRET_3', 'secret content 3'),
                ]),
                'expected' => new ArrayCollection([
                    new EnvironmentVariable('env-var-name-1', 'secret content 1'),
                    new EnvironmentVariable('env-var-name-2', 'secret content 1'),
                    new EnvironmentVariable('env-var-name-3', 'secret content 2'),
                    new EnvironmentVariable('env-var-name-4', 'secret content 3'),
                ]),
            ],
        ];
    }
}
