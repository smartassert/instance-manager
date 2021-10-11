<?php

namespace App\Model\InstanceSorter;

use App\Model\Instance;

interface InstanceSorterInterface
{
    public function sort(Instance $a, Instance $b): int;
}
