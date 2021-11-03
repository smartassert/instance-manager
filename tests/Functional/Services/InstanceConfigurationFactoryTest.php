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
     *
     * @param string[] $tags
     */
    public function testCreate(string $postCreateScript, array $tags, Configuration $expected): void
    {
        self::assertEquals($expected, $this->instanceConfigurationFactory->create($postCreateScript, $tags));
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        return [
            'no post-create script, no tags' => [
                'postCreateScript' => '',
                'tags' => [],
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
                    [],
                ),
            ],
            'has post-create script, no tags' => [
                'postCreateScript' => './scripts/post-create.sh',
                'tags' => [],
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
                    [],
                ),
            ],
            'no post-create script, has tags' => [
                'postCreateScript' => '',
                'tags' => [
                    'instance-collection-tag-value',
                    'instance-tag-value',
                ],
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
                        'instance-tag-value',
                    ],
                ),
            ],
            'has post-create script, has tags' => [
                'postCreateScript' => './scripts/post-create.sh',
                'tags' => [
                    'instance-collection-tag-value',
                    'instance-tag-value',
                ],
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
                        'instance-tag-value',
                    ],
                ),
            ],
        ];
    }
}
