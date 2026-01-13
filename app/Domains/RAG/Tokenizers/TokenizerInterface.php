<?php

namespace App\Domains\RAG\Tokenizers;

/**
 * Contract for token counting and measurement.
 *
 * Follows Interface Segregation Principle (ISP): Only token-related methods.
 */
interface TokenizerInterface
{
    /**
     * Count tokens in text.
     *
     * @param string $text The text to count tokens for
     * @return int Number of tokens
     */
    public function countTokens(string $text): int;

    /**
     * Get text length in tokens (for chunking purposes).
     *
     * @param string $text The text to measure
     * @return int Token length
     */
    public function getTokenLength(string $text): int;
}
