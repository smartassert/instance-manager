<?php

declare(strict_types=1);

namespace App\Exception;

use App\Command\Option;

class ServiceIdMissingException extends \Exception
{
    public function __construct()
    {
        parent::__construct('"' . Option::OPTION_SERVICE_ID . '" option empty');
    }
}
