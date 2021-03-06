<?php

declare(strict_types=1);

namespace App\Model;

use DigitalOceanV2\Entity\Droplet;

class Instance implements \JsonSerializable
{
    public const DROPLET_STATUS_NEW = 'new';
    public const DROPLET_STATUS_ACTIVE = 'active';
    public const DROPLET_STATUS_OFF = 'off';
    public const DROPLET_STATUS_ARCHIVE = 'archive';
    public const DROPLET_STATUS_UNKNOWN = 'unknown';

    public function __construct(private Droplet $droplet)
    {
    }

    public function getId(): int
    {
        return $this->droplet->id;
    }

    public function getDroplet(): Droplet
    {
        return $this->droplet;
    }

    /**
     * @return self::DROPLET_STATUS_*
     */
    public function getDropletStatus(): string
    {
        $dropletStatus = $this->droplet->status;

        if (
            self::DROPLET_STATUS_NEW === $dropletStatus
            || self::DROPLET_STATUS_ACTIVE === $dropletStatus
            || self::DROPLET_STATUS_OFF === $dropletStatus
            || self::DROPLET_STATUS_ARCHIVE === $dropletStatus
        ) {
            return $dropletStatus;
        }

        return self::DROPLET_STATUS_UNKNOWN;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getState(): array
    {
        return [
            'ips' => $this->getIps(),
            'created_at' => $this->getCreatedAt(),
        ];
    }

    public function getUrl(): ?string
    {
        $ip = $this->getFirstPublicV4IpAddress();
        if (null === $ip) {
            return null;
        }

        return sprintf('http://%s', $ip);
    }

    public function hasIp(string $ip): bool
    {
        foreach ($this->droplet->networks as $network) {
            if ($ip === $network->ipAddress) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function getIps(): array
    {
        $ips = [];

        foreach ($this->droplet->networks as $network) {
            $ips[] = $network->ipAddress;
        }

        return array_unique($ips);
    }

    public function getLabel(): string
    {
        $tagsComponent = implode(', ', $this->droplet->tags);
        if ('' === $tagsComponent) {
            $tagsComponent = '[no tags]';
        }

        return sprintf(
            '%s (%s)',
            $this->getId(),
            $tagsComponent,
        );
    }

    public function getCreatedAt(): string
    {
        return $this->droplet->createdAt;
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'state' => $this->getState(),
        ];
    }

    public function getFirstPublicV4IpAddress(): ?string
    {
        foreach ($this->droplet->networks as $network) {
            if (4 === $network->version && 'public' === $network->type) {
                return $network->ipAddress;
            }
        }

        return null;
    }
}
