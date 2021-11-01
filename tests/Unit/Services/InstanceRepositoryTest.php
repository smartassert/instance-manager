<?php

namespace App\Tests\Unit\Services;

use App\Model\Instance;
use App\Services\InstanceRepository;
use DigitalOceanV2\Api\Droplet as DropletApi;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SmartAssert\DigitalOceanDropletConfiguration\Factory as DropletConfigurationFactory;

class InstanceRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(
        DropletConfigurationFactory $dropletConfigurationFactory,
        string $serviceToken,
        string $postCreateScript,
        string $expectedUserData,
    ): void {
        $dropletEntity = new DropletEntity();
        $expectedDropletApiCreateNames = 'worker-manager-0.4.2';

        $dropletApi = \Mockery::mock(DropletApi::class);
        $dropletApi
            ->shouldReceive('create')
            ->withArgs(function (
                $names,
                string $region,
                string $size,
                $image,
                bool $backups,
                bool $ipv6,
                $vpcUuid,
                array $sshKeys = [],
                string $userData = ''
            ) use (
                $expectedDropletApiCreateNames,
                $expectedUserData
            ) {
                self::assertSame($expectedDropletApiCreateNames, $names);
                self::assertSame($expectedUserData, $userData);

                return true;
            })
            ->andReturn($dropletEntity)
        ;

        $instanceRepository = new InstanceRepository(
            $dropletApi,
            $dropletConfigurationFactory,
            'worker-manager',
            'worker-manager-0.4.2'
        );

        $instance = $instanceRepository->create($serviceToken, $postCreateScript);

        self::assertInstanceOf(Instance::class, $instance);
        self::assertSame($dropletEntity, $instance->getDroplet());
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        return [
            'no default user data, no post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory(),
                'serviceToken' => 'non-empty-service-token',
                'postDeployScript' => '',
                'expectedCreatedUserData' => '# Default user data' . "\n" .
                    '# No default user data' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    '# No post-create script',
            ],
            'has default single-line user data, no post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory([
                    DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                ]),
                'serviceToken' => 'non-empty-service-token',
                'postDeployScript' => '',
                'expectedCreatedUserData' => '# Default user data' . "\n" .
                    'echo "single-line user data"' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    '# No post-create script',
            ],
            'has default multi-line user data, no post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory([
                    DropletConfigurationFactory::KEY_USER_DATA => 'echo "multi-line user data 1"' . "\n" .
                        'echo "multi-line user data 2"' . "\n" .
                        'echo "multi-line user data 3"'
                ]),
                'serviceToken' => 'non-empty-service-token',
                'postDeployScript' => '',
                'expectedCreatedUserData' => '# Default user data' . "\n" .
                    'echo "multi-line user data 1"' . "\n" .
                    'echo "multi-line user data 2"' . "\n" .
                    'echo "multi-line user data 3"' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    '# No post-create script',
            ],
            'no default user data, has post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory(),
                'serviceToken' => 'non-empty-service-token',
                'postDeployScript' => './scripts/post-create.sh',
                'expectedCreatedUserData' => '# Default user data' . "\n" .
                    '# No default user data' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    './scripts/post-create.sh',
            ],
            'has default single-line user data, has post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory([
                    DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                ]),
                'serviceToken' => 'non-empty-service-token',
                'postDeployScript' => './scripts/post-create.sh',
                'expectedCreatedUserData' => '# Default user data' . "\n" .
                    'echo "single-line user data"' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    './scripts/post-create.sh',
            ],
            'has default multi-line user data, has post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory([
                    DropletConfigurationFactory::KEY_USER_DATA => 'echo "multi-line user data 1"' . "\n" .
                        'echo "multi-line user data 2"' . "\n" .
                        'echo "multi-line user data 3"'
                ]),
                'serviceToken' => 'non-empty-service-token',
                'postDeployScript' => './scripts/post-create.sh',
                'expectedCreatedUserData' => '# Default user data' . "\n" .
                    'echo "multi-line user data 1"' . "\n" .
                    'echo "multi-line user data 2"' . "\n" .
                    'echo "multi-line user data 3"' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    './scripts/post-create.sh',
            ],
        ];
    }

    public function testFindAll(): void
    {
        $droplets = [
            new DropletEntity([
                'id' => 123,
            ]),
            new DropletEntity([
                'id' => 456,
            ]),
        ];

        $dropletApi = \Mockery::mock(DropletApi::class);
        $dropletApi
            ->shouldReceive('getAll')
            ->with('worker-manager')
            ->andReturn($droplets)
        ;

        $instanceRepository = new InstanceRepository(
            $dropletApi,
            \Mockery::mock(DropletConfigurationFactory::class),
            'worker-manager',
            'worker-manager-123456'
        );

        $instances = $instanceRepository->findAll();

        self::assertCount(count($droplets), $instances);

        foreach ($instances as $instanceId => $instance) {
            self::assertSame($droplets[$instanceId]->id, $instance->getId());
        }
    }
}
