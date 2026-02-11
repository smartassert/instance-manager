<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\AssignedIp;
use App\Model\Instance;
use DigitalOceanV2\Api\FloatingIp as FloatingIpApi;
use DigitalOceanV2\Entity\Action as ActionEntity;
use DigitalOceanV2\Exception\ExceptionInterface;

class FloatingIpManager
{
    public function __construct(
        private FloatingIpApi $floatingIpApi
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function create(Instance $instance): AssignedIp
    {
        $floatingIpEntity = $this->floatingIpApi->createAssigned($instance->getId());
        if (!isset($floatingIpEntity->droplet)) {
            $floatingIpEntity->droplet = $instance->getDroplet();
        }

        return new AssignedIp($floatingIpEntity);
    }

    /**
     * @throws ExceptionInterface
     */
    public function reAssign(Instance $instance, string $ip): ActionEntity
    {
        return $this->floatingIpApi->assign($ip, $instance->getId());
    }
}
