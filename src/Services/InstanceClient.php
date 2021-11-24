<?php

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
        private string $stateUrl,
        private ServiceConfiguration $serviceConfiguration,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     *
     * @return array<int|string, mixed>
     */
    public function getState(Instance $instance): array
    {
        $url = $instance->getUrl() . $this->stateUrl;
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
    public function getHealth(string $serviceId, Instance $instance): ResponseInterface
    {
        $url = $instance->getUrl() . $this->serviceConfiguration->getHealthCheckUrl($serviceId);
        $request = $this->requestFactory->createRequest('GET', $url);

        return $this->httpClient->sendRequest($request);
    }
}
