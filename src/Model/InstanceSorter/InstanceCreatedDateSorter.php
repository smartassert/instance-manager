<?php

declare(strict_types=1);

namespace App\Model\InstanceSorter;

use App\Model\Instance;

class InstanceCreatedDateSorter implements InstanceSorterInterface
{
    public function sort(Instance $a, Instance $b): int
    {
        $aTimestamp = (new \DateTimeImmutable($a->getCreatedAt()))->getTimestamp();
        $bTimestamp = (new \DateTimeImmutable($b->getCreatedAt()))->getTimestamp();

        if ($aTimestamp === $bTimestamp) {
            return 0;
        }

        return $aTimestamp < $bTimestamp ? 1 : -1;
    }
}
