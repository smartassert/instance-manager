<?php

namespace App\Tests\Unit\Services;

use App\Model\Instance;
use App\Services\InstanceConfigurationFactory;
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
        InstanceConfigurationFactory $instanceConfigurationFactory,
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
            $instanceConfigurationFactory,
            'worker-manager',
            'worker-manager-0.4.2'
        );

        $instance = $instanceRepository->create($postCreateScript);

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
                'instanceConfigurationFactory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory(),
                ),
                'postDeployScript' => '',
                'expectedCreatedUserData' => '# Post-create script' . "\n" .
                    '# No post-create script',
            ],
            'has default user data, no post-create script' => [
                'instanceConfigurationFactory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                    ])
                ),
                'postDeployScript' => '',
                'expectedCreatedUserData' => 'echo "single-line user data"' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    '# No post-create script',
            ],
            'no default user data, has post-create script' => [
                'instanceConfigurationFactory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory()
                ),
                'postDeployScript' => './scripts/post-create.sh',
                'expectedCreatedUserData' => '# Post-create script' . "\n" .
                    './scripts/post-create.sh',
            ],
            'has default user data, has post-create script' => [
                'instanceConfigurationFactory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                    ])
                ),
                'postDeployScript' => './scripts/post-create.sh',
                'expectedCreatedUserData' => 'echo "single-line user data"' . "\n" .
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
            \Mockery::mock(InstanceConfigurationFactory::class),
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
