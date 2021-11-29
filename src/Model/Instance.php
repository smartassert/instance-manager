<?php

declare(strict_types=1);

namespace App\Model;

use DigitalOceanV2\Entity\Droplet;

class Instance implements \JsonSerializable
{
    private const DROPLET_STATUS_NEW = 'new';
    private const DROPLET_STATUS_ACTIVE = 'active';
    private const DROPLET_STATUS_OFF = 'off';
    private const DROPLET_STATUS_ARCHIVE = 'archive';
    private const DROPLET_STATUS_UNKNOWN = 'unknown';

    /**
     * @var array<int|string, mixed>
     */
    private array $state = [];

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
        return array_merge(
            $this->state,
            [
                'ips' => $this->getIps(),
            ]
        );
    }

    /**
     * @param array<int|string, mixed> $state
     */
    public function withAdditionalState(array $state): self
    {
        $new = clone $this;
        $new->state = $state;

        return $new;
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

    public function getCreatedAt(): \DateTimeInterface
    {
        return new \DateTimeImmutable($this->droplet->createdAt);
    }

    public function isMatchedBy(Filter $filter): bool
    {
        $state = $this->getState();
        $field = $filter->getField();

        if (array_key_exists($field, $state)) {
            $stateValue = $state[$field];
            $value = $filter->getValue();
            $matchType = $filter->getMatchType();

            if (is_scalar($stateValue)) {
                return FilterInterface::MATCH_TYPE_POSITIVE === $matchType && $stateValue === $value
                     || FilterInterface::MATCH_TYPE_NEGATIVE === $matchType && $stateValue !== $value;
            }

            if (is_array($stateValue)) {
                return FilterInterface::MATCH_TYPE_POSITIVE === $matchType && in_array($value, $stateValue)
                    || FilterInterface::MATCH_TYPE_NEGATIVE === $matchType && !in_array($value, $stateValue);
            }
        }

        return FilterInterface::MATCH_TYPE_POSITIVE !== $filter->getMatchType();
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

    private function getFirstPublicV4IpAddress(): ?string
    {
        foreach ($this->droplet->networks as $network) {
            if (4 === $network->version && 'public' === $network->type) {
                return $network->ipAddress;
            }
        }

        return null;
    }
}
