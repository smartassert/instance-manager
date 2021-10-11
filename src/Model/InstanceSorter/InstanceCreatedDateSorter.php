<?php

namespace App\Model\InstanceSorter;

use App\Model\Instance;

class InstanceCreatedDateSorter implements InstanceSorterInterface
{
    public function sort(Instance $a, Instance $b): int
    {
        $aTimestamp = $a->getCreatedAt()->getTimestamp();
        $bTimestamp = $b->getCreatedAt()->getTimestamp();

        if ($aTimestamp === $bTimestamp) {
            return 0;
        }

        return $aTimestamp < $bTimestamp ? 1 : -1;
    }
}
