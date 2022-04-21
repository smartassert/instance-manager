<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Exception\ServiceIdMissingException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

trait MissingServiceIdTestTrait
{
    public function testRunWithoutServiceIdThrowsException(): void
    {
        $this->expectExceptionObject(new ServiceIdMissingException());
        $this->command->run(new ArrayInput([]), new NullOutput());
    }
}
