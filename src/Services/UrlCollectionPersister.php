<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Filename;
use App\Model\Service\UrlCollection;

readonly class UrlCollectionPersister implements UrlCollectionPersisterInterface
{
    public function __construct(
        private ServiceConfigurationOperator $serviceConfigurationOperator,
    ) {}

    public function persist(string $serviceId, UrlCollection $urlCollection): bool
    {
        return $this->serviceConfigurationOperator->persist(
            $serviceId,
            Filename::URL_COLLECTION->value,
            (string) json_encode($urlCollection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
