<?php

namespace App\Services;

use App\Model\AssignedIp;
use DigitalOceanV2\Api\FloatingIp as FloatingIpApi;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ExceptionInterface;

class FloatingIpRepository
{
    public function __construct(
        private FloatingIpApi $floatingIpApi,
    ) {
    }

    /**
     * Find the floating IP used by the active instance.
     *
     * @throws ExceptionInterface
     */
    public function find(string $collectionTag): ?AssignedIp
    {
        $floatingIpEntities = $this->floatingIpApi->getAll();

        foreach ($floatingIpEntities as $floatingIpEntity) {
            $assignee = $floatingIpEntity->droplet;

            if ($assignee instanceof DropletEntity) {
                if (in_array($collectionTag, $assignee->tags)) {
                    return new AssignedIp($floatingIpEntity);
                }
            }
        }

        return null;
    }
}
