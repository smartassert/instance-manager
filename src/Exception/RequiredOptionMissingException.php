<?php

declare(strict_types=1);

namespace App\Exception;

class RequiredOptionMissingException extends \Exception
{
    public function __construct(string $name)
    {
        parent::__construct('"' . $name . '" option empty');
    }
}
