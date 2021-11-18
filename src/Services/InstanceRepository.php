<?php

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
    public function create(string $collectionTag, string $imageId, string $firstBootScript): Instance
    {
        $tag = $this->instanceTagFactory->create($collectionTag, $imageId);

        $configuration = $this->instanceConfigurationFactory->create($firstBootScript, [$collectionTag, $tag]);

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
    public function findAll(string $collectionTag): InstanceCollection
    {
        return $this->findWithTag($collectionTag);
    }

    /**
     * @throws ExceptionInterface
     */
    public function findCurrent(string $collectionTag, string $imageId): ?Instance
    {
        $tag = $this->instanceTagFactory->create($collectionTag, $imageId);

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
