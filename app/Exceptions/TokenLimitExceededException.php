<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when token limit is exceeded.
 */
class TokenLimitExceededException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'Token limit exceeded',
        int $code = 429,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception with detailed token information.
     *
     * @param int $requestedTokens Tokens requested
     * @param int $currentUsage Current token usage
     * @param int $tokenLimit Token limit
     * @param bool $allowOverages Whether overages are allowed
     * @return self
     */
    public static function withTokenDetails(
        int $requestedTokens,
        int $currentUsage,
        int $tokenLimit,
        bool $allowOverages = false
    ): self {
        $remaining = max(0, $tokenLimit - $currentUsage);
        $message = sprintf(
            'Token limit exceeded. Requested: %d tokens, Current usage: %d tokens, Limit: %d tokens, Remaining: %d tokens%s',
            $requestedTokens,
            $currentUsage,
            $tokenLimit,
            $remaining,
            $allowOverages ? ' (overages allowed)' : ' (overages not allowed)'
        );

        return new self($message, 429);
    }
}
