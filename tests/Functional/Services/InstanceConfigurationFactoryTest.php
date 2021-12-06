<?php

declare(strict_types=1);

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
    public function testCreate(string $firstBootScript, array $tags, Configuration $expected): void
    {
        self::assertEquals($expected, $this->instanceConfigurationFactory->create($firstBootScript, $tags));
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        return [
            'no first-boot script, no tags' => [
                'firstBootScript' => '',
                'tags' => [],
                'expected' => new Configuration(
                    [],
                    'lon1',
                    's-1vcpu-1gb',
                    '',
                    false,
                    false,
                    false,
                    [],
                    '',
                    true,
                    [],
                    [],
                ),
            ],
            'has first-boot script, no tags' => [
                'firstBootScript' => './scripts/first-boot.sh',
                'tags' => [],
                'expected' => new Configuration(
                    [],
                    'lon1',
                    's-1vcpu-1gb',
                    '',
                    false,
                    false,
                    false,
                    [],
                    './scripts/first-boot.sh',
                    true,
                    [],
                    [],
                ),
            ],
            'no first-boot script, has tags' => [
                'firstBootScript' => '',
                'tags' => [
                    'service-id-value',
                    'instance-tag-value',
                ],
                'expected' => new Configuration(
                    [],
                    'lon1',
                    's-1vcpu-1gb',
                    '',
                    false,
                    false,
                    false,
                    [],
                    '',
                    true,
                    [],
                    [
                        'service-id-value',
                        'instance-tag-value',
                    ],
                ),
            ],
            'has first-boot script, has tags' => [
                'firstBootScript' => './scripts/first-boot.sh',
                'tags' => [
                    'service-id-value',
                    'instance-tag-value',
                ],
                'expected' => new Configuration(
                    [],
                    'lon1',
                    's-1vcpu-1gb',
                    '',
                    false,
                    false,
                    false,
                    [],
                    './scripts/first-boot.sh',
                    true,
                    [],
                    [
                        'service-id-value',
                        'instance-tag-value',
                    ],
                ),
            ],
        ];
    }
}
