<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\Option;
use App\Exception\RequiredOptionMissingException;
use App\Tests\Mock\MockServiceConfiguration;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

trait MissingInstanceIdTestTrait
{
    /**
     * @dataProvider runWithoutInstanceIdThrowsExceptionDataProvider
     *
     * @param array<mixed> $input
     */
    public function testRunWithoutInstanceIdThrowsException(string $serviceId, array $input): void
    {
        ObjectReflector::setProperty(
            $this->command,
            $this->command::class,
            'serviceConfiguration',
            (new MockServiceConfiguration())
                ->withExistsCall($serviceId, true)
                ->withGetHealthCheckUrlCall($serviceId, '/health-check')
                ->withGetStateUrlCall($serviceId, '/state')
                ->getMock()
        );

        $this->expectExceptionObject(new RequiredOptionMissingException(Option::OPTION_ID));

        $this->command->run(new ArrayInput($input), new NullOutput());
    }

    /**
     * @return array<mixed>
     */
    public function runWithoutInstanceIdThrowsExceptionDataProvider(): array
    {
        $serviceId = 'service_id';
        $foo = $this::getInputExcludingInstanceId();

        return [
            'missing' => [
                'serviceId' => $serviceId,
                'input' => $foo,
            ],
            'not numeric' => [
                'serviceId' => $serviceId,
                'input' => array_merge(
                    $foo,
                    [
                        '--' . Option::OPTION_ID => 'not-numeric',
                    ]
                ),
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    abstract protected static function getInputExcludingInstanceId(): array;
}
