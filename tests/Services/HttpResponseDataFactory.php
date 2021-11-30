<?php

declare(strict_types=1);

namespace App\Tests\Services;

class HttpResponseDataFactory
{
    /**
     * @param array<mixed> $data
     *
     * @return array{"status-code": int, "headers": array<string>, "body": string}
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
