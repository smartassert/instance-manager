<?php

declare(strict_types=1);

namespace App\Model\InstanceSorter;

use App\Model\Instance;

interface InstanceSorterInterface
{
    public function sort(Instance $a, Instance $b): int;
}
