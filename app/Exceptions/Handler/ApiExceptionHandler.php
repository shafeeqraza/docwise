<?php

namespace App\Exceptions\Handler;

use App\Http\Controllers\V1\Concerns\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiExceptionHandler
{
    use ResponseHandler;

    /**
     * Get a singleton instance to use trait methods.
     *
     * @return static
     */
    private static function instance(): static
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new static();
        }
        return $instance;
    }

    /**
     * Check if request is an API route or expects JSON.
     * Prioritizes route path checking over headers to catch all API routes.
     *
     * @param Request $request
     * @return bool
     */
    public static function isApiRequest(Request $request): bool
    {
        // First, check route path patterns - this is the most reliable method
        // This ensures API routes are detected even without Accept headers
        $path = $request->path();
        $pathInfo = $request->getPathInfo();
        $uri = $request->getRequestUri();
        $applicationJson = "application/json";
        $apiPrefix = "api/";

        return $request->is('api/*')
            || str_starts_with($path, $apiPrefix)
            || str_starts_with($pathInfo, $apiPrefix)
            || str_starts_with($uri, $apiPrefix)
            || str_contains($pathInfo, $apiPrefix)
            || str_contains($uri, $apiPrefix)
            || $request->expectsJson()
            || $request->wantsJson()
            || $request->header('Accept') === $applicationJson
            || $request->header('Content-Type') === $applicationJson
            || str_contains($request->header('Accept', ''), $applicationJson);
    }

    /**
     * Format standard error response.
     *
     * @param string $message
     * @param int $statusCode
     * @param array $additionalData
     * @return JsonResponse
     */
    public static function errorResponse(string $message, int $statusCode, mixed $errors = [], array $additionalData = []): JsonResponse
    {
        return self::instance()
            ->respondError($message, $statusCode, $errors, $additionalData);
    }

    /**
     * Format unauthenticated response.
     *
     * @return JsonResponse
     */
    public static function unauthenticated(): JsonResponse
    {
        return self::errorResponse('Unauthenticated', 401);
    }

    /**
     * Format validation error response.
     *
     * @param array $errors
     * @return JsonResponse
     */
    public static function validationError(array $errors): JsonResponse
    {
        return self::errorResponse('Validation failed', 422, $errors);
    }

    /**
     * Format not found response.
     *
     * @return JsonResponse
     */
    public static function notFound(): JsonResponse
    {
        return self::errorResponse('Resource not found', 404);
    }

    /**
     * Format forbidden response.
     *
     * @return JsonResponse
     */
    public static function forbidden(): JsonResponse
    {
        return self::errorResponse('Forbidden', 403);
    }

    /**
     * Format HTTP exception response.
     *
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function httpError(string $message, int $statusCode): JsonResponse
    {
        return self::errorResponse($message ?: 'An error occurred', $statusCode);
    }

    /**
     * Format generic exception response.
     *
     * @param \Throwable $e
     * @return JsonResponse
     */
    public static function genericError(\Throwable $e): JsonResponse
    {
        $statusCode = 500;
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            $statusCode = $e->getStatusCode();
        }

        $message = config('app.debug') ? $e->getMessage() : 'An error occurred';
        $additionalData = [];

        if (config('app.debug')) {
            $additionalData = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
        }

        return self::errorResponse($message, $statusCode, [], $additionalData);
    }
}
