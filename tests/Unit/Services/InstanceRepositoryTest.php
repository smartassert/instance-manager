<?php

namespace App\Tests\Unit\Services;

use App\Model\Instance;
use App\Services\InstanceRepository;
use DigitalOceanV2\Api\Droplet as DropletApi;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SmartAssert\DigitalOceanDropletConfiguration\Factory;

class InstanceRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testCreate(): void
    {
        $dropletConfigurationFactory = new Factory();
        $dropletEntity = new DropletEntity();

        $dropletApi = \Mockery::mock(DropletApi::class);
        $dropletApi
            ->shouldReceive('create')
            ->with(
                'worker-manager-0.4.2',
                '',
                '',
                '',
                false,
                false,
                false,
                [],
                '',
                true,
                [],
                [],
            )
            ->andReturn($dropletEntity)
        ;

        $instanceRepository = new InstanceRepository(
            $dropletApi,
            $dropletConfigurationFactory,
            'worker-manager',
            'worker-manager-0.4.2'
        );

        $instance = $instanceRepository->create();

        self::assertInstanceOf(Instance::class, $instance);
        self::assertSame($dropletEntity, $instance->getDroplet());
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
            \Mockery::mock(Factory::class),
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
