<?php

namespace App\Tests\Unit\Services;

use App\Services\InstanceConfigurationFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SmartAssert\DigitalOceanDropletConfiguration\Configuration;
use SmartAssert\DigitalOceanDropletConfiguration\Factory as DropletConfigurationFactory;

class InstanceConfigurationFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @dataProvider createDataProvider
     *
     * @param string[] $tags
     */
    public function testCreate(
        InstanceConfigurationFactory $factory,
        string $postCreateScript,
        array $tags,
        Configuration $expected
    ): void {
        $configuration = $factory->create($postCreateScript, $tags);

        self::assertEquals($expected, $configuration);
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $instanceCollectionTag = 'instance-collection-tag-value';
        $instanceTag = 'instance-tag-value';

        $tags = [$instanceCollectionTag, $instanceTag];

        return [
            'no default user data, no post-create script, no tags' => [
                'factory' => new InstanceConfigurationFactory(new DropletConfigurationFactory()),
                'postDeployScript' => '',
                'tags' => [],
                'expected' => new Configuration(
                    [],
                    '',
                    '',
                    '',
                    false,
                    false,
                    false,
                    [],
                    '# Post-create script' . "\n" .
                    '# No post-create script',
                    true,
                    [],
                    [],
                ),
            ],
            'no default user data, no post-create script' => [
                'factory' => new InstanceConfigurationFactory(new DropletConfigurationFactory()),
                'postDeployScript' => '',
                'tags' => $tags,
                'expected' => new Configuration(
                    [],
                    '',
                    '',
                    '',
                    false,
                    false,
                    false,
                    [],
                    '# Post-create script' . "\n" .
                    '# No post-create script',
                    true,
                    [],
                    $tags,
                ),
            ],
            'has default single-line user data, no post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                    ])
                ),
                'postDeployScript' => '',
                'tags' => $tags,
                'expected' => new Configuration(
                    [],
                    '',
                    '',
                    '',
                    false,
                    false,
                    false,
                    [],
                    'echo "single-line user data"' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    '# No post-create script',
                    true,
                    [],
                    $tags,
                ),
            ],
            'has default multi-line user data, no post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "multi-line user data 1"' . "\n" .
                            'echo "multi-line user data 2"' . "\n" .
                            'echo "multi-line user data 3"'
                    ])
                ),
                'postDeployScript' => '',
                'tags' => $tags,
                'expected' => new Configuration(
                    [],
                    '',
                    '',
                    '',
                    false,
                    false,
                    false,
                    [],
                    'echo "multi-line user data 1"' . "\n" .
                    'echo "multi-line user data 2"' . "\n" .
                    'echo "multi-line user data 3"' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    '# No post-create script',
                    true,
                    [],
                    $tags,
                ),
            ],
            'no default user data, has post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory()
                ),
                'postDeployScript' => './scripts/post-create.sh',
                'tags' => $tags,
                'expected' => new Configuration(
                    [],
                    '',
                    '',
                    '',
                    false,
                    false,
                    false,
                    [],
                    '# Post-create script' . "\n" .
                    './scripts/post-create.sh',
                    true,
                    [],
                    $tags,
                ),
            ],
            'has default single-line user data, has post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                    ])
                ),
                'postDeployScript' => './scripts/post-create.sh',
                'tags' => $tags,
                'expected' => new Configuration(
                    [],
                    '',
                    '',
                    '',
                    false,
                    false,
                    false,
                    [],
                    'echo "single-line user data"' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    './scripts/post-create.sh',
                    true,
                    [],
                    $tags,
                ),
            ],
            'has default multi-line user data, has post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "multi-line user data 1"' . "\n" .
                            'echo "multi-line user data 2"' . "\n" .
                            'echo "multi-line user data 3"'
                    ])
                ),
                'postDeployScript' => './scripts/post-create.sh',
                'tags' => $tags,
                'expected' => new Configuration(
                    [],
                    '',
                    '',
                    '',
                    false,
                    false,
                    false,
                    [],
                    'echo "multi-line user data 1"' . "\n" .
                    'echo "multi-line user data 2"' . "\n" .
                    'echo "multi-line user data 3"' . "\n" .
                    '' . "\n" .
                    '# Post-create script' . "\n" .
                    './scripts/post-create.sh',
                    true,
                    [],
                    $tags,
                ),
            ],
        ];
    }
}
