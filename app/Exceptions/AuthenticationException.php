<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\V1\Concerns\ResponseHandler;

class AuthenticationException extends Exception
{
    use ResponseHandler;
    /**
     * Create an exception for invalid credentials.
     *
     * @return static
     */
    public static function invalidCredentials(): static
    {
        return new static('Invalid credentials', 401, null, 'INVALID_CREDENTIALS');
    }

    /**
     * Create an exception for deactivated account.
     *
     * @return static
     */
    public static function accountDeactivated(): static
    {
        return new static('Account is deactivated', 403, null, 'ACCOUNT_DEACTIVATED');
    }

    /**
     * Create an exception for unauthorized access.
     *
     * @return static
     */
    public static function unauthorized(): static
    {
        return new static('Unauthorized', 401, null, 'UNAUTHORIZED');
    }

    /**
     * Create an exception for forbidden access.
     *
     * @return static
     */
    public static function forbidden(): static
    {
        return new static('Forbidden', 403, null, 'FORBIDDEN');
    }

    /**
     * Create an exception for too many login attempts.
     *
     * @param int $remainingMinutes
     * @return static
     */
    public static function tooManyAttempts(int $remainingMinutes): static
    {
        return new static(
            "Too many login attempts. Please try again in {$remainingMinutes} minutes.",
            429,
            null,
            'TOO_MANY_ATTEMPTS'
        );
    }

    /**
     * Error code for the exception.
     *
     * @var string|null
     */
    protected ?string $errorCode = null;

    /**
     * Create a new exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param string|null $errorCode
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, ?string $errorCode = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }

    /**
     * Get the error code.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function render($request): JsonResponse
    {
        $status = $this->getCode() ?: 401;
        return $this->respondError($this->getMessage(), $status);
    }
}
