<?php

namespace App\Http\Controllers\V1\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

trait ResponseHandler
{
    /**
     * Standard API response wrapper.
     */
    protected function respond(
        mixed $data = [],
        string $message = 'OK',
        int $statusCode = 200,
        array $headers = []
    ): JsonResponse {
        return (new JsonResponse(
            data: [
                'message' => $message,
                'success' => $statusCode < 400,
                'statusCode' => $statusCode,
                'data' => $data ?? [],
            ],
            status: $statusCode,
            headers: $headers,
            options: JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * Standard success response wrapper (enveloped).
     */
    protected function respondSuccess(mixed $data = [], string $message = 'OK', int $statusCode = 200, array $headers = []): JsonResponse
    {
        return $this->respond(
            data: $data ?? [],
            message: $message,
            statusCode: $statusCode,
            headers: $headers
        );
    }

    /**
     * Standard error response wrapper (enveloped).
     */
    protected function respondError(string $message, int $statusCode = 400, mixed $data = [], array $headers = []): JsonResponse
    {
        return $this->respond(
            data: $data ?? [],
            message: $message,
            statusCode: $statusCode,
            headers: $headers
        );
    }

    /**
     * Standard message response wrapper (enveloped).
     */
    protected function respondMessage(string $message, int $statusCode = 200, mixed $data = [], array $headers = []): JsonResponse
    {
        return $this->respond(
            data: $data ?? [],
            message: $message,
            statusCode: $statusCode,
            headers: $headers
        );
    }

    /**
     * Return a JsonResource as a response in the standard envelope.
     */
    protected function respondResource(
        JsonResource $resource,
        string $message = 'OK',
        int $statusCode = 200,
        array $headers = []
    ): JsonResponse {
        return $this->respond(
            data: $resource,
            message: $message,
            statusCode: $statusCode,
            headers: $headers
        );
    }
}
