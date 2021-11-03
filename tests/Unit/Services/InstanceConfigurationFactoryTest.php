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
     */
    public function testCreate(
        InstanceConfigurationFactory $factory,
        string $postCreateScript,
        Configuration $expected
    ): void {
        $configuration = $factory->create($postCreateScript);

        self::assertEquals($expected, $configuration);
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $instanceCollectionTag = 'instance-collection-tag-value';
        $instanceTag = 'instance-tag-value';

        return [
            'no default user data, no post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory(),
                    $instanceCollectionTag,
                    $instanceTag
                ),
                'postDeployScript' => '',
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
                    [
                        $instanceCollectionTag,
                        $instanceTag,
                    ],
                ),
            ],
            'has default single-line user data, no post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                    ]),
                    $instanceCollectionTag,
                    $instanceTag
                ),
                'postDeployScript' => '',
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
                    [
                        $instanceCollectionTag,
                        $instanceTag,
                    ],
                ),
            ],
            'has default multi-line user data, no post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "multi-line user data 1"' . "\n" .
                            'echo "multi-line user data 2"' . "\n" .
                            'echo "multi-line user data 3"'
                    ]),
                    $instanceCollectionTag,
                    $instanceTag
                ),
                'postDeployScript' => '',
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
                    [
                        $instanceCollectionTag,
                        $instanceTag,
                    ],
                ),
            ],
            'no default user data, has post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory(),
                    $instanceCollectionTag,
                    $instanceTag
                ),
                'postDeployScript' => './scripts/post-create.sh',
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
                    [
                        $instanceCollectionTag,
                        $instanceTag,
                    ],
                ),
            ],
            'has default single-line user data, has post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                    ]),
                    $instanceCollectionTag,
                    $instanceTag
                ),
                'postDeployScript' => './scripts/post-create.sh',
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
                    [
                        $instanceCollectionTag,
                        $instanceTag,
                    ],
                ),
            ],
            'has default multi-line user data, has post-create script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "multi-line user data 1"' . "\n" .
                            'echo "multi-line user data 2"' . "\n" .
                            'echo "multi-line user data 3"'
                    ]),
                    $instanceCollectionTag,
                    $instanceTag
                ),
                'postDeployScript' => './scripts/post-create.sh',
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
                    [
                        $instanceCollectionTag,
                        $instanceTag,
                    ],
                ),
            ],
        ];
    }
}
