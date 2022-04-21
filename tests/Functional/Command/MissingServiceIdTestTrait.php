<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\Option;
use App\Exception\RequiredOptionMissingException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

trait MissingServiceIdTestTrait
{
    public function testRunWithoutServiceIdThrowsException(): void
    {
        $this->expectExceptionObject(new RequiredOptionMissingException(Option::OPTION_SERVICE_ID));
        $this->command->run(new ArrayInput([]), new NullOutput());
    }
}
