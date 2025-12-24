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
        mixed $body = [],
        int $statusCode = 200,
        array $headers = [],
    ): JsonResponse {
        return new JsonResponse(
            data: $body,
            status: $statusCode,
            headers: $headers,
            options: JSON_UNESCAPED_SLASHES,
        );
    }

    protected function buildResponseBody(mixed $data = [], string $message = 'OK', int $statusCode = 200, array $errors = [], array $additionalData = []): array
    {
        return [
            'success' => $statusCode < 400,
            'message' => $message,
            ...$additionalData,
            ...(count($errors) ? compact('errors') : []),
            ...($statusCode < 400 ? compact('data') : []),
        ];
    }

    /**
     * Standard success response wrapper (enveloped).
     */
    protected function respondSuccess(mixed $data = [], string $message = 'OK', int $statusCode = 200, array $headers = []): JsonResponse
    {
        return $this->respond(
            body: $this->buildResponseBody(data: $data, message: $message, statusCode: $statusCode),
            statusCode: $statusCode,
            headers: $headers,
        );
    }

    /**
     * Standard error response wrapper (enveloped).
     */
    protected function respondError(string $message, int $statusCode = 400, mixed $errors = [], array $additionalData = []): JsonResponse
    {
        $body = $this->buildResponseBody(
            errors: $errors,
            message: $message,
            statusCode: $statusCode,
            additionalData: $additionalData
        );

        return $this->respond(
            body: $body,
            statusCode: $statusCode,
            headers: [],
        );
    }

    /**
     * Standard message response wrapper (enveloped).
     */
    protected function respondMessage(string $message, int $statusCode = 200, mixed $data = [], array $headers = []): JsonResponse
    {
        return $this->respond(
            body: $this->buildResponseBody(data: $data, message: $message, statusCode: $statusCode),
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
            body: $this->buildResponseBody(data: $resource, message: $message, statusCode: $statusCode),
            statusCode: $statusCode,
            headers: $headers
        );
    }
}
