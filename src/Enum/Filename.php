<?php

declare(strict_types=1);

namespace App\Enum;

enum Filename: string
{
    case URL_COLLECTION = 'configuration.json';
    case IMAGE = 'image.json';
    case DOMAIN = 'domain.json';
    case ENVIRONMENT_VARIABLES = 'env.json';
}
