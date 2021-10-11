<?php

namespace App\Services;

class OutputFactory
{
    /**
     * @param array<mixed> $data
     */
    public function createSuccessOutput(array $data = []): string
    {
        return $this->createOutput('success', $data);
    }

    /**
     * @param array<mixed> $data
     */
    public function createErrorOutput(string $errorCode, array $data = []): string
    {
        return $this->createOutput('error', array_merge(
            ['error-code' => $errorCode],
            $data
        ));
    }

    /**
     * @param 'error'|'success' $status
     * @param array<mixed>      $data
     */
    private function createOutput(string $status, array $data = []): string
    {
        return (string) json_encode(array_merge(
            [
                'status' => $status,
            ],
            $data
        ));
    }
}
