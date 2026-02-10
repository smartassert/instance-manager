<?php

declare(strict_types=1);

namespace App\Tests\Services;

/**
 * @phpstan-import-type HttpResponseData from HttpResponseFactory
 */
class HttpResponseDataFactory
{
    /**
     * @param array<mixed> $data
     *
     * @return HttpResponseData
     */
    public static function createJsonResponseData(array $data, int $statusCode = 200): array
    {
        return [
            HttpResponseFactory::KEY_STATUS_CODE => $statusCode,
            HttpResponseFactory::KEY_HEADERS => [
                'content-type' => 'application/json; charset=UTF-8',
            ],
            HttpResponseFactory::KEY_BODY => (string) json_encode($data),
        ];
    }
}
