<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Instance;

class InstanceRouteGenerator
{
    public const HOST_PLACEHOLDER = '{{ host }}';

    public function createHealthCheckUrl(string $healthCheckUrl, Instance $instance): string
    {
        return $this->replaceInstanceHostInUrl($instance, $healthCheckUrl);
    }

    public function createStateUrl(string $stateUrl, Instance $instance): string
    {
        return $this->replaceInstanceHostInUrl($instance, $stateUrl);
    }

    private function replaceInstanceHostInUrl(Instance $instance, string $url): string
    {
        if ('' === $url) {
            return '';
        }

        $ipAddress = $instance->getFirstPublicV4IpAddress();
        if (null === $ipAddress) {
            return '';
        }

        return str_replace(self::HOST_PLACEHOLDER, $ipAddress, $url);
    }
}
