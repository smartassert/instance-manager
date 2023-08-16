<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Filename;
use App\Model\Service\UrlCollection;

interface UrlCollectionPersisterInterface
{
    public function persist(string $serviceId, UrlCollection $urlCollection): bool;
}
