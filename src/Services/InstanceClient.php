<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Instance;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class InstanceClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private InstanceRouteGenerator $routeGenerator,
    ) {
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws ClientExceptionInterface
     */
    public function getState(string $stateUrl, Instance $instance): array
    {
        $url = $this->routeGenerator->createStateUrl($stateUrl, $instance);
        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);

        if (str_starts_with($response->getHeaderLine('content-type'), 'application/json')) {
            $data = json_decode($response->getBody()->getContents(), true);
        } else {
            $data = [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function getHealth(string $healthCheckUrl, Instance $instance): ResponseInterface
    {
        $url = $this->routeGenerator->createHealthCheckUrl($healthCheckUrl, $instance);
        $request = $this->requestFactory->createRequest('GET', $url);

        return $this->httpClient->sendRequest($request);
    }
}
