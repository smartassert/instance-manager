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
        string $firstBootScript,
        array $tags,
        Configuration $expected
    ): void {
        $configuration = $factory->create($firstBootScript, $tags);

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
            'no default user data, no first-boot script, no tags' => [
                'factory' => new InstanceConfigurationFactory(new DropletConfigurationFactory()),
                'firstBootScript' => '',
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
                    '# First-boot script' . "\n" .
                    '# No first-boot script',
                    true,
                    [],
                    [],
                ),
            ],
            'no default user data, no first-boot script' => [
                'factory' => new InstanceConfigurationFactory(new DropletConfigurationFactory()),
                'firstBootScript' => '',
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
                    '# First-boot script' . "\n" .
                    '# No first-boot script',
                    true,
                    [],
                    $tags,
                ),
            ],
            'has default single-line user data, no first-boot script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                    ])
                ),
                'firstBootScript' => '',
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
                    '# First-boot script' . "\n" .
                    '# No first-boot script',
                    true,
                    [],
                    $tags,
                ),
            ],
            'has default multi-line user data, no first-boot script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "multi-line user data 1"' . "\n" .
                            'echo "multi-line user data 2"' . "\n" .
                            'echo "multi-line user data 3"'
                    ])
                ),
                'firstBootScript' => '',
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
                    '# First-boot script' . "\n" .
                    '# No first-boot script',
                    true,
                    [],
                    $tags,
                ),
            ],
            'no default user data, has first-boot script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory()
                ),
                'firstBootScript' => './scripts/first-boot.sh',
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
                    '# First-boot script' . "\n" .
                    './scripts/first-boot.sh',
                    true,
                    [],
                    $tags,
                ),
            ],
            'has default single-line user data, has first-boot script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                    ])
                ),
                'firstBootScript' => './scripts/first-boot.sh',
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
                    '# First-boot script' . "\n" .
                    './scripts/first-boot.sh',
                    true,
                    [],
                    $tags,
                ),
            ],
            'has default multi-line user data, has first-boot script' => [
                'factory' => new InstanceConfigurationFactory(
                    new DropletConfigurationFactory([
                        DropletConfigurationFactory::KEY_USER_DATA => 'echo "multi-line user data 1"' . "\n" .
                            'echo "multi-line user data 2"' . "\n" .
                            'echo "multi-line user data 3"'
                    ])
                ),
                'firstBootScript' => './scripts/first-boot.sh',
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
                    '# First-boot script' . "\n" .
                    './scripts/first-boot.sh',
                    true,
                    [],
                    $tags,
                ),
            ],
        ];
    }
}
