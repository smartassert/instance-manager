<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\Option;
use App\Exception\InstanceNotFoundException;
use App\Tests\Mock\MockServiceConfiguration;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

trait MissingInstanceTestTrait
{
    public function testRunWithNonExistentInstanceThrowsException(): void
    {
        $serviceId = 'service_id';

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

        $this->mockHandler->append(new Response(404));

        $this->expectExceptionObject(new InstanceNotFoundException(123));

        $input = array_merge(
            $this::getInputExcludingInstanceId(),
            [
                '--' . Option::OPTION_ID => '123',
            ]
        );

        $this->command->run(new ArrayInput($input), new NullOutput());
    }

    /**
     * @return array<mixed>
     */
    abstract protected static function getInputExcludingInstanceId(): array;
}
