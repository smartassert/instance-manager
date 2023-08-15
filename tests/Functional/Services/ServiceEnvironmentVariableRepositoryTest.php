<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Exception\MissingSecretException;
use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableCollection;
use App\Services\DomainLoaderInterface;
use App\Services\ServiceConfiguration;
use App\Services\ServiceEnvironmentVariableRepository;
use App\Tests\Mock\MockServiceConfiguration;
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
     */
    public function testGetCollectionSuccess(
        EnvironmentVariableCollection $serviceConfigurationEnvironmentVariables,
        string $secretsJson,
        string $serviceConfigurationDomain,
        EnvironmentVariableCollection $expectedEnvironmentVariables,
    ): void {
        $this->setServiceConfiguration((new MockServiceConfiguration())
            ->withGetImageIdCall(self::SERVICE_ID, self::IMAGE_ID)
            ->withGetEnvironmentVariablesCall(self::SERVICE_ID, $serviceConfigurationEnvironmentVariables)
            ->withGetDomainCall(self::SERVICE_ID, $serviceConfigurationDomain)
            ->getMock());

        $domainLoader = \Mockery::mock(DomainLoaderInterface::class);
        $domainLoader
            ->shouldReceive('load')
            ->with(self::SERVICE_ID)
            ->andReturn($serviceConfigurationDomain)
        ;

        ObjectReflector::setProperty($this->repository, $this->repository::class, 'domainLoader', $domainLoader);

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
                'serviceConfigurationEnvironmentVariables' => new EnvironmentVariableCollection(),
                'secretsJson' => '',
                'serviceConfigurationDomain' => 'example.com',
                'expectedEnvironmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable(
                        ServiceEnvironmentVariableRepository::NAME_DOMAIN,
                        'example.com',
                    ),
                ]),
            ],
            'service configuration env vars, no secrets' => [
                'serviceConfigurationEnvironmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                ]),
                'secretsJson' => '',
                'serviceConfigurationDomain' => 'example.com',
                'expectedEnvironmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'value1'),
                    new EnvironmentVariable('key2', 'value2'),
                    new EnvironmentVariable(
                        ServiceEnvironmentVariableRepository::NAME_DOMAIN,
                        'example.com',
                    )
                ]),
            ],
            'service-prefixed matching secrets' => [
                'serviceConfigurationEnvironmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', '{{ secrets.SERVICE_ID_SECRET_001 }}'),
                    new EnvironmentVariable('key2', 'value2'),
                ]),
                'secretsJson' => '{"SERVICE_ID_SECRET_001":"secret 001 value"}',
                'serviceConfigurationDomain' => 'example.com',
                'expectedEnvironmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'secret 001 value'),
                    new EnvironmentVariable('key2', 'value2'),
                    new EnvironmentVariable(
                        ServiceEnvironmentVariableRepository::NAME_DOMAIN,
                        'example.com',
                    )
                ]),
            ],
            'service- and common-prefixed matching secrets' => [
                'serviceConfigurationEnvironmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', '{{ secrets.SERVICE_ID_SECRET_001 }}'),
                    new EnvironmentVariable('key2', 'value2'),
                    new EnvironmentVariable('key3', '{{ secrets.COMMON_VALUE }}'),
                ]),
                'secretsJson' => '{"SERVICE_ID_SECRET_001":"secret 001 value", "COMMON_VALUE":"common secret value"}',
                'serviceConfigurationDomain' => 'example.com',
                'expectedEnvironmentVariables' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', 'secret 001 value'),
                    new EnvironmentVariable('key2', 'value2'),
                    new EnvironmentVariable('key3', 'common secret value'),
                    new EnvironmentVariable(
                        ServiceEnvironmentVariableRepository::NAME_DOMAIN,
                        'example.com',
                    )
                ]),
            ],
        ];
    }

    /**
     * @dataProvider getCollectionThrowsMissingSecretExceptionDataProvider
     */
    public function testGetCollectionThrowsMissingSecretException(
        string $secretsJsonOption,
        EnvironmentVariableCollection $environmentVariables,
        string $expectedExceptionMessage
    ): void {
        $this->setServiceConfiguration((new MockServiceConfiguration())
            ->withGetImageIdCall(self::SERVICE_ID, self::IMAGE_ID)
            ->withGetEnvironmentVariablesCall(self::SERVICE_ID, $environmentVariables)
            ->getMock());

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
                'environmentVariableList' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', '{{ secrets.SERVICE_ID_SECRET_001 }}')
                ]),
                'expectedExceptionMessage' => 'Secret "SERVICE_ID_SECRET_001" not found',
            ],
            'has secrets, env var references missing secret not having service id as prefix' => [
                'secretsJson' => '',

                'environmentVariableList' => new EnvironmentVariableCollection([
                    new EnvironmentVariable('key1', '{{ secrets.DIFFERENT_SERVICE_ID_SECRET_001 }}')
                ]),
                'expectedExceptionMessage' => 'Secret "DIFFERENT_SERVICE_ID_SECRET_001" not found',
            ],
        ];
    }

    private function setServiceConfiguration(ServiceConfiguration $serviceConfiguration): void
    {
        ObjectReflector::setProperty(
            $this->repository,
            $this->repository::class,
            'serviceConfiguration',
            $serviceConfiguration
        );
    }
}
