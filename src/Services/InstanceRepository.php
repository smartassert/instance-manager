<?php

namespace App\Services;

use App\Model\ExcludeNotFoundDropletAction;
use App\Model\Instance;
use App\Model\InstanceCollection;
use DigitalOceanV2\Api\Droplet as DropletApi;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ExceptionInterface;

class InstanceRepository
{
    public function __construct(
        private DropletApi $dropletApi,
        private InstanceConfigurationFactory $instanceConfigurationFactory,
        private InstanceTagFactory $instanceTagFactory,
        private string $instanceTag,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function create(string $collectionTag, string $imageId, string $postCreateScript): Instance
    {
        $tag = $this->instanceTagFactory->create($collectionTag, $imageId);

        $configuration = $this->instanceConfigurationFactory->create($postCreateScript, [$collectionTag, $tag]);

        $dropletEntity = $this->dropletApi->create(
            $tag,
            $configuration->getRegion(),
            $configuration->getSize(),
            $configuration->getImage(),
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
    public function findCurrent(): ?Instance
    {
        return $this->findWithTag($this->instanceTag)->getNewest();
    }

    /**
     * @throws ExceptionInterface
     */
    public function find(int $id): ?Instance
    {
        return (new ExcludeNotFoundDropletAction(function (int $id) {
            return new Instance($this->dropletApi->getById($id));
        }))($id);
    }

    /**
     * @throws ExceptionInterface
     */
    public function delete(int $id): void
    {
        (new ExcludeNotFoundDropletAction(function (int $id) {
            $this->dropletApi->remove($id);
        }))($id);
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
