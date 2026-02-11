<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\InstanceConfigurationFactory;
use App\Services\InstanceRepository;
use App\Services\InstanceTagFactory;
use DigitalOceanV2\Api\Droplet as DropletApi;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmartAssert\DigitalOceanDropletConfiguration\Factory as DropletConfigurationFactory;

class InstanceRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[DataProvider('createDataProvider')]
    public function testCreate(
        InstanceConfigurationFactory $instanceConfigurationFactory,
        string $serviceId,
        int $imageId,
        string $firstBootScript,
        string $expectedUserData,
    ): void {
        $dropletEntity = new DropletEntity();

        $expectedTag = $serviceId . '-' . $imageId;

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
                array $sshKeys,
                string $userData,
                bool $monitoring,
                array $volumes,
                array $tags,
            ) use (
                $expectedUserData,
                $serviceId,
                $expectedTag,
                $imageId
            ) {
                self::assertSame($imageId, $image);
                self::assertSame($expectedTag, $names);
                self::assertSame($expectedUserData, $userData);
                self::assertSame([$serviceId, $expectedTag], $tags);

                return true;
            })
            ->andReturn($dropletEntity)
        ;

        $instanceRepository = new InstanceRepository(
            $dropletApi,
            $instanceConfigurationFactory,
            new InstanceTagFactory()
        );

        $instance = $instanceRepository->create($serviceId, $imageId, $firstBootScript);

        self::assertSame($dropletEntity, $instance->getDroplet());
    }

    /**
     * @return array<mixed>
     */
    public static function createDataProvider(): array
    {
        $serviceId = 'service_id';
        $imageId = 123456;

        return [
            'no first-boot script' => [
                'instanceConfigurationFactory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory()
                ),
                'serviceId' => $serviceId,
                'imageId' => $imageId,
                'firstBootScript' => '',
                'expectedUserData' => '',
            ],
            'has first-boot script' => [
                'instanceConfigurationFactory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory()
                ),
                'serviceId' => $serviceId,
                'imageId' => $imageId,
                'firstBootScript' => './scripts/first-boot.sh',
                'expectedUserData' => './scripts/first-boot.sh',
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

        $serviceId = 'service_id';

        $dropletApi = \Mockery::mock(DropletApi::class);
        $dropletApi
            ->shouldReceive('getAll')
            ->with($serviceId)
            ->andReturn($droplets)
        ;

        $instanceRepository = new InstanceRepository(
            $dropletApi,
            \Mockery::mock(InstanceConfigurationFactory::class),
            new InstanceTagFactory()
        );

        $instances = $instanceRepository->findAll($serviceId);

        self::assertCount(count($droplets), $instances);

        foreach ($instances as $instanceId => $instance) {
            self::assertSame($droplets[$instanceId]->id, $instance->getId());
        }
    }
}
