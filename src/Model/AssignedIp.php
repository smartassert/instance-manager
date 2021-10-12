<?php

namespace App\Model;

use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Entity\FloatingIp as FloatingIpEntity;

class AssignedIp
{
    private Instance $instance;

    public function __construct(
        private FloatingIpEntity $floatingIpEntity,
    ) {
        $dropletEntity = $floatingIpEntity->droplet;
        if ($dropletEntity instanceof DropletEntity) {
            $this->instance = new Instance($dropletEntity);
        }
    }

    public function getInstance(): Instance
    {
        return $this->instance;
    }

    public function getIp(): string
    {
        return $this->floatingIpEntity->ip;
    }
}
