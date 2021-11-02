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
        DropletConfigurationFactory $dropletConfigurationFactory,
        string $postCreateScript,
        Configuration $expected
    ): void {
        $instanceConfigurationFactory = new InstanceConfigurationFactory($dropletConfigurationFactory);

        $configuration = $instanceConfigurationFactory->create($postCreateScript);

        self::assertEquals($expected, $configuration);
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        return [
            'no default user data, no post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory(),
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
                    [],
                ),
            ],
            'has default single-line user data, no post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory([
                    DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                ]),
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
                    [],
                ),
            ],
            'has default multi-line user data, no post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory([
                    DropletConfigurationFactory::KEY_USER_DATA => 'echo "multi-line user data 1"' . "\n" .
                        'echo "multi-line user data 2"' . "\n" .
                        'echo "multi-line user data 3"'
                ]),
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
                    [],
                ),
            ],
            'no default user data, has post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory(),
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
                    [],
                ),
            ],
            'has default single-line user data, has post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory([
                    DropletConfigurationFactory::KEY_USER_DATA => 'echo "single-line user data"'
                ]),
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
                    [],
                ),
            ],
            'has default multi-line user data, has post-create script' => [
                'dropletConfigurationFactory' => new DropletConfigurationFactory([
                    DropletConfigurationFactory::KEY_USER_DATA => 'echo "multi-line user data 1"' . "\n" .
                        'echo "multi-line user data 2"' . "\n" .
                        'echo "multi-line user data 3"'
                ]),
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
                    [],
                ),
            ],
        ];
    }
}
