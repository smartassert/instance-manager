<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\Option;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Services\ServiceConfiguration;
use App\Tests\Mock\MockServiceConfiguration;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use webignition\ObjectReflector\ObjectReflector;

trait MissingImageIdTestTrait
{
    use MockeryPHPUnitIntegration;

    public function testRunWithoutServiceConfigurationFileThrowsException(): void
    {
        $serviceId = 'service_id';

        $this->expectExceptionObject(
            new ServiceConfigurationMissingException($serviceId, ServiceConfiguration::IMAGE_FILENAME)
        );

        $this->command->run(new ArrayInput(['--' . Option::OPTION_SERVICE_ID => $serviceId]), new NullOutput());
    }

    public function testRunWithoutImageIdThrowsException(): void
    {
        $serviceId = 'service_id';

        $exception = new ConfigurationFileValueMissingException(
            ServiceConfiguration::IMAGE_FILENAME,
            'image_id',
            'service_id'
        );

        ObjectReflector::setProperty(
            $this->command,
            $this->command::class,
            'serviceConfiguration',
            (new MockServiceConfiguration())
                ->withExistsCall($serviceId, true)
                ->withGetImageIdCall($serviceId, $exception)
                ->getMock()
        );

        $this->expectExceptionObject($exception);
        $this->command->run(new ArrayInput(['--' . Option::OPTION_SERVICE_ID => $serviceId]), new NullOutput());
    }
}
