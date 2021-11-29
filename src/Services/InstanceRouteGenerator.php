<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Instance;
use App\Model\ServiceConfiguration as ServiceConfigurationModel;

class InstanceRouteGenerator
{
    public const HOST_PLACEHOLDER = '{{ host }}';

    public function createHealthCheckUrl(ServiceConfigurationModel $serviceConfiguration, Instance $instance): string
    {
        return $this->replaceInstanceHostInUrl($instance, $serviceConfiguration->getHealthCheckUrl());
    }

    public function createStateUrl(ServiceConfigurationModel $serviceConfiguration, Instance $instance): string
    {
        return $this->replaceInstanceHostInUrl($instance, $serviceConfiguration->getStateUrl());
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
