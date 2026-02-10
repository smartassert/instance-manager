<?php

declare(strict_types=1);

namespace App\Tests\Services;

class DropletDataFactory
{
    /**
     * @param array<mixed> $dropletData
     *
     * @return array<mixed>
     */
    public static function normalize(array $dropletData): array
    {
        return self::normalizeDropletData($dropletData);
    }

    /**
     * @param string[] $ips
     *
     * @return array<mixed>
     */
    public static function createWithIps(int $id, array $ips): array
    {
        $v4NetworksData = [];

        foreach ($ips as $ip) {
            $v4NetworksData[] = [
                'ip_address' => $ip,
                'type' => 'public',
            ];
        }

        return [
            'id' => $id,
            'networks' => [
                'v4' => $v4NetworksData,
            ],
        ];
    }

    /**
     * @param array<mixed> $dropletData
     *
     * @return array<mixed>
     */
    private static function normalizeDropletData(array $dropletData): array
    {
        if (array_key_exists('networks', $dropletData)) {
            $networksData = $dropletData['networks'];
            if (is_array($networksData)) {
                if (array_key_exists('v4', $networksData) && is_array($networksData['v4'])) {
                    $networksData['v4'] = self::normalizeNetworksCollectionData($networksData['v4']);
                }

                if (array_key_exists('v6', $networksData) && is_array($networksData['v6'])) {
                    $networksData['v6'] = self::normalizeNetworksCollectionData($networksData['v6']);
                }

                $networksData = (object) $networksData;
            }

            $dropletData['networks'] = $networksData;
        }

        return $dropletData;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function normalizeNetworksCollectionData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = (object) $value;
            }

            $data[$key] = $value;
        }

        return $data;
    }
}
