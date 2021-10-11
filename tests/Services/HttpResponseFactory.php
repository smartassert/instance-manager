<?php

namespace App\Tests\Services;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class HttpResponseFactory
{
    public const KEY_STATUS_CODE = 'status-code';
    public const KEY_HEADERS = 'headers';
    public const KEY_BODY = 'body';

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function create(int $statusCode, array $headers = [], ?string $body = null): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($statusCode);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        if (is_string($body)) {
            $bodyStream = $this->streamFactory->createStream($body);
            $response = $response->withBody($bodyStream);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromArray(array $data): ResponseInterface
    {
        $statusCode = $data[self::KEY_STATUS_CODE] ?? 200;
        $statusCode = is_int($statusCode) ? $statusCode : 200;

        $headers = $data[self::KEY_HEADERS] ?? [];
        $headers = is_array($headers) ? $headers : [];

        $body = $data[self::KEY_BODY] ?? null;
        $body = is_string($body) ? $body : null;

        return $this->create($statusCode, $headers, $body);
    }
}
