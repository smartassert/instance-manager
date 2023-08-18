<?php

declare(strict_types=1);

namespace App\Enum;

enum UrlKey: string
{
    case HEALTH_CHECK = 'health_check_url';
    case STATE = 'state_url';
}
