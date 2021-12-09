<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Exception\MissingSecretException;
use App\Model\EnvironmentVariable;
use App\Services\ServiceConfiguration;
use App\Services\ServiceEnvironmentVariableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use webignition\ObjectReflector\ObjectReflector;

class ServiceEnvironmentVariableRepositoryTest extends KernelTestCase
{
    use MockeryPHPUnitIntegration;

    private const SERVICE_ID = 'service_id';
    private const IMAGE_ID = '12345';

    private ServiceEnvironmentVariableRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(ServiceEnvironmentVariableRepository::class);
        \assert($repository instanceof ServiceEnvironmentVariableRepository);
        $this->repository = $repository;
    }

    /**
     * @dataProvider getCollectionSuccessDataProvider
     *
     * @param Collection<int, EnvironmentVariable> $serviceConfigurationEnvironmentVariables
     * @param Collection<int, EnvironmentVariable> $expectedEnvironmentVariables
     */
    public function testGetCollectionSuccess(
        Collection $serviceConfigurationEnvironmentVariables,
        string $secretsJson,
        Collection $expectedEnvironmentVariables,
    ): void {
        $this->mockServiceConfiguration(self::IMAGE_ID, $serviceConfigurationEnvironmentVariables);

        $environmentVariables = $this->repository->getCollection(self::SERVICE_ID, $secretsJson);

        self::assertEquals($expectedEnvironmentVariables, $environmentVariables);
    }

    /**
     * @return array<mixed>
     */
    public function getCollectionSuccessDataProvider(): array
    {
        return [
            'no service configuration env vars, no secrets' => [
                'serviceConfigurationEnvironmentVariables' => new ArrayCollection(),
                'secretsJson' => '',
                'expectedEnvironmentVariables' => new ArrayCollection(),
            ],
            'service configuration env vars, no secrets' => [
                'serviceConfigurationEnvironmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                ]),
                'secretsJson' => '',
                'expectedEnvironmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                ]),
            ],
            'service configuration env vars with secret placeholders, has matching secrets' => [
                'serviceConfigurationEnvironmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('key1', '{{ secrets.SERVICE_ID_SECRET_001 }}'),
                    new EnvironmentVariable('key2', 'value2'),
                ]),
                'secretsJson' => '{"SERVICE_ID_SECRET_001":"secret 001 value"}',
                'expectedEnvironmentVariables' => new ArrayCollection([
                    new EnvironmentVariable('key1', 'secret 001 value'),
                    new EnvironmentVariable('key2', 'value2'),
                ]),
            ],
        ];
    }

    /**
     * @dataProvider getCollectionThrowsMissingSecretExceptionDataProvider
     *
     * @param Collection<int, EnvironmentVariable> $environmentVariables
     */
    public function testGetCollectionThrowsMissingSecretException(
        string $secretsJsonOption,
        Collection $environmentVariables,
        string $expectedExceptionMessage
    ): void {
        $this->mockServiceConfiguration(self::IMAGE_ID, $environmentVariables);

        self::expectException(MissingSecretException::class);
        self::expectExceptionMessage($expectedExceptionMessage);

        $this->repository->getCollection(self::SERVICE_ID, '');
    }

    /**
     * @return array<mixed>
     */
    public function getCollectionThrowsMissingSecretExceptionDataProvider(): array
    {
        return [
            'no secrets, env var references missing secret' => [
                'secretsJson' => '',
                'environmentVariableList' => new ArrayCollection([
                    new EnvironmentVariable('key1', '{{ secrets.SERVICE_ID_SECRET_001 }}')
                ]),
                'expectedExceptionMessage' => 'Secret "SERVICE_ID_SECRET_001" not found',
            ],
            'has secrets, env var references missing secret not having service id as prefix' => [
                'secretsJson' => '',

                'environmentVariableList' => new ArrayCollection([
                    new EnvironmentVariable('key1', '{{ secrets.DIFFERENT_SERVICE_ID_SECRET_001 }}')
                ]),
                'expectedExceptionMessage' => 'Secret "DIFFERENT_SERVICE_ID_SECRET_001" not found',
            ],
        ];
    }

    /**
     * @param null|Collection<int, EnvironmentVariable> $environmentVariables
     */
    private function mockServiceConfiguration(
        ?string $imageId,
        ?Collection $environmentVariables = null
    ): void {
        $serviceConfiguration = \Mockery::mock(ServiceConfiguration::class);
        $serviceConfiguration
            ->shouldReceive('getImageId')
            ->with(self::SERVICE_ID)
            ->andReturn($imageId)
        ;

        if ($environmentVariables instanceof Collection) {
            $serviceConfiguration
                ->shouldReceive('getEnvironmentVariables')
                ->with(self::SERVICE_ID)
                ->andReturn($environmentVariables)
            ;
        }

        ObjectReflector::setProperty(
            $this->repository,
            $this->repository::class,
            'serviceConfiguration',
            $serviceConfiguration
        );
    }
}
