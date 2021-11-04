<?php

namespace App\Tests\Unit\Services;

use App\Model\Instance;
use App\Services\InstanceConfigurationFactory;
use App\Services\InstanceRepository;
use App\Services\InstanceTagFactory;
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
        string $collectionTag,
        string $imageId,
        string $firstBootScript,
        string $expectedUserData,
    ): void {
        $dropletEntity = new DropletEntity();

        $expectedTag = $collectionTag . '-' . $imageId;

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
                $collectionTag,
                $expectedTag,
                $imageId
            ) {
                self::assertSame($imageId, $image);
                self::assertSame($expectedTag, $names);
                self::assertSame($expectedUserData, $userData);
                self::assertSame([$collectionTag, $expectedTag], $tags);

                return true;
            })
            ->andReturn($dropletEntity)
        ;

        $instanceRepository = new InstanceRepository(
            $dropletApi,
            $instanceConfigurationFactory,
            new InstanceTagFactory()
        );

        $instance = $instanceRepository->create($collectionTag, $imageId, $firstBootScript);

        self::assertInstanceOf(Instance::class, $instance);
        self::assertSame($dropletEntity, $instance->getDroplet());
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $collectionTag = 'service-id';
        $imageId = '123456';

        return [
            'no default user data, no first-boot script' => [
                'instanceConfigurationFactory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory()
                ),
                'collectionTag' => $collectionTag,
                'imageId' => $imageId,
                'firstBootScript' => '',
                'expectedCreatedUserData' => '# First-boot script' . "\n" .
                    '# No first-boot script',
            ],
            'has default user data, no first-boot script' => [
                'instanceConfigurationFactory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                    ])
                ),
                'collectionTag' => $collectionTag,
                'imageId' => $imageId,
                'firstBootScript' => '',
                'expectedCreatedUserData' => 'echo "single-line user data"' . "\n" .
                    '' . "\n" .
                    '# First-boot script' . "\n" .
                    '# No first-boot script',
            ],
            'no default user data, has first-boot script' => [
                'instanceConfigurationFactory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory()
                ),
                'collectionTag' => $collectionTag,
                'imageId' => $imageId,
                'firstBootScript' => './scripts/first-boot.sh',
                'expectedCreatedUserData' => '# First-boot script' . "\n" .
                    './scripts/first-boot.sh',
            ],
            'has default user data, has first-boot script' => [
                'instanceConfigurationFactory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                    ])
                ),
                'collectionTag' => $collectionTag,
                'imageId' => $imageId,
                'firstBootScript' => './scripts/first-boot.sh',
                'expectedCreatedUserData' => 'echo "single-line user data"' . "\n" .
                    '' . "\n" .
                    '# First-boot script' . "\n" .
                    './scripts/first-boot.sh',
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

        $collectionTag = 'service-id';

        $dropletApi = \Mockery::mock(DropletApi::class);
        $dropletApi
            ->shouldReceive('getAll')
            ->with($collectionTag)
            ->andReturn($droplets)
        ;

        $instanceRepository = new InstanceRepository(
            $dropletApi,
            \Mockery::mock(InstanceConfigurationFactory::class),
            new InstanceTagFactory()
        );

        $instances = $instanceRepository->findAll($collectionTag);

        self::assertCount(count($droplets), $instances);

        foreach ($instances as $instanceId => $instance) {
            self::assertSame($droplets[$instanceId]->id, $instance->getId());
        }
    }
}
