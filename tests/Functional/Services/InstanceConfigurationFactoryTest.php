<?php

namespace App\Tests\Functional\Services;

use App\Services\InstanceConfigurationFactory;
use SmartAssert\DigitalOceanDropletConfiguration\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InstanceConfigurationFactoryTest extends KernelTestCase
{
    private InstanceConfigurationFactory $instanceConfigurationFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $instanceRepository = self::getContainer()->get(InstanceConfigurationFactory::class);
        \assert($instanceRepository instanceof InstanceConfigurationFactory);
        $this->instanceConfigurationFactory = $instanceRepository;
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(string $postCreateScript, Configuration $expected): void
    {
        self::assertEquals($expected, $this->instanceConfigurationFactory->create($postCreateScript));
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        return [
            'no post-create script' => [
                'postCreateScript' => '',
                'expected' => new Configuration(
                    [],
                    'lon1',
                    's-1vcpu-1gb',
                    'image-id-test',
                    false,
                    false,
                    false,
                    [],
                    '# Post-create script' . "\n" . '# No post-create script',
                    true,
                    [],
                    [
                        'instance-collection-tag-value',
                        'instance-collection-tag-value-image-id-test',
                    ],
                ),
            ],
            'has post-create script' => [
                'postCreateScript' => './scripts/post-create.sh',
                'expected' => new Configuration(
                    [],
                    'lon1',
                    's-1vcpu-1gb',
                    'image-id-test',
                    false,
                    false,
                    false,
                    [],
                    '# Post-create script' . "\n" . './scripts/post-create.sh',
                    true,
                    [],
                    [
                        'instance-collection-tag-value',
                        'instance-collection-tag-value-image-id-test',
                    ],
                ),
            ],
        ];
    }
}
