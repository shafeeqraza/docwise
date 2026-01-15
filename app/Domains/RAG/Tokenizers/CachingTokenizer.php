<?php

namespace App\Domains\RAG\Tokenizers;

/**
 * Decorator for tokenizers that adds in-memory caching to avoid duplicate API calls.
 * Especially useful for API-based tokenizers like Gemini.
 */
class CachingTokenizer implements TokenizerInterface
{
    /**
     * In-memory cache for token counts.
     *
     * @var array<string, int>
     */
    private array $cache = [];

    /**
     * Create a new CachingTokenizer instance.
     *
     * @param TokenizerInterface $tokenizer The underlying tokenizer to wrap
     */
    public function __construct(
        private TokenizerInterface $tokenizer
    ) {}

    /**
     * Count tokens in text, using cache if available.
     *
     * @param string $text The text to count tokens for
     * @return int Number of tokens
     */
    public function countTokens(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Use a hash of the text as cache key to save memory
        $key = md5($text);

        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->tokenizer->countTokens($text);
        }

        return $this->cache[$key];
    }

    /**
     * Get text length in tokens, using cache if available.
     *
     * @param string $text The text to measure
     * @return int Token length
     */
    public function getTokenLength(string $text): int
    {
        return $this->countTokens($text);
    }

    /**
     * Clear the cache to free memory.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get the underlying tokenizer.
     *
     * @return TokenizerInterface
     */
    public function getUnderlyingTokenizer(): TokenizerInterface
    {
        return $this->tokenizer;
    }
}
