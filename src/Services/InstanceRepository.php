<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Instance;
use App\Model\InstanceCollection;
use DigitalOceanV2\Api\Droplet as DropletApi;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ExceptionInterface;
use DigitalOceanV2\Exception\RuntimeException;

class InstanceRepository
{
    public function __construct(
        private DropletApi $dropletApi,
        private InstanceConfigurationFactory $instanceConfigurationFactory,
        private InstanceTagFactory $instanceTagFactory,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function create(string $serviceId, int $imageId, string $firstBootScript): Instance
    {
        $tag = $this->instanceTagFactory->create($serviceId, $imageId);

        $configuration = $this->instanceConfigurationFactory->create($firstBootScript, [$serviceId, $tag]);

        $dropletEntity = $this->dropletApi->create(
            $tag,
            $configuration->getRegion(),
            $configuration->getSize(),
            $imageId,
            $configuration->getBackups(),
            $configuration->getIpv6(),
            $configuration->getVpcUuid(),
            $configuration->getSshKeys(),
            $configuration->getUserData(),
            $configuration->getMonitoring(),
            $configuration->getVolumes(),
            $configuration->getTags()
        );

        return new Instance($dropletEntity instanceof DropletEntity ? $dropletEntity : new DropletEntity());
    }

    /**
     * @throws ExceptionInterface
     */
    public function findAll(string $serviceId): InstanceCollection
    {
        return $this->findWithTag($serviceId);
    }

    /**
     * @throws ExceptionInterface
     */
    public function findCurrent(string $serviceId, int $imageId): ?Instance
    {
        $tag = $this->instanceTagFactory->create($serviceId, $imageId);

        return $this->findWithTag($tag)->getNewest();
    }

    /**
     * @throws ExceptionInterface
     */
    public function find(int $id): ?Instance
    {
        try {
            return new Instance($this->dropletApi->getById($id));
        } catch (ExceptionInterface $exception) {
            if ($exception instanceof RuntimeException && 404 === $exception->getCode()) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @throws RuntimeException
     */
    public function delete(int $id): void
    {
        try {
            $this->dropletApi->remove($id);
        } catch (ExceptionInterface $exception) {
            if ($exception instanceof RuntimeException && 404 !== $exception->getCode()) {
                throw $exception;
            }
        }
    }

    /**
     * @throws ExceptionInterface
     */
    private function findWithTag(string $tag): InstanceCollection
    {
        $dropletEntities = $this->dropletApi->getAll($tag);

        $instances = [];
        foreach ($dropletEntities as $dropletEntity) {
            $instances[] = new Instance($dropletEntity);
        }

        return new InstanceCollection($instances);
    }
}
