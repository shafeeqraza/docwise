<?php

namespace App\Services\V1\Common;

/**
 * Fast local token estimation service.
 *
 * Uses character-based heuristics for quick validation.
 * NOT accurate for billing - use TokenizerFactory for exact counts.
 *
 * Follows Single Responsibility Principle (SRP): Fast estimation only.
 */
class TokenEstimator
{
    /**
     * Estimate token count using character-based heuristics.
     *
     * Average ratios by model:
     * - English text: ~4 chars/token
     * - Code: ~3.5 chars/token
     * - Mixed: ~4 chars/token
     *
     * @param string $text The text to estimate
     * @return int Estimated token count (conservative)
     */
    public function estimate(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));
        $charCount = mb_strlen($text);

        // Use 4 chars/token (conservative estimate)
        return (int) ceil($charCount / 4);
    }

    /**
     * Check if text is within token limit (fast validation).
     *
     * @param string $text The text to check
     * @param int $maxTokens Maximum allowed tokens
     * @return bool True if within limit
     */
    public function isWithinLimit(string $text, int $maxTokens): bool
    {
        return $this->estimate($text) <= $maxTokens;
    }

    /**
     * Estimate total tokens for multiple texts.
     *
     * @param array<string> $texts Array of text strings
     * @return int Total estimated tokens
     */
    public function estimateMultiple(array $texts): int
    {
        $total = 0;
        foreach ($texts as $text) {
            $total += $this->estimate($text);
        }
        return $total;
    }
}
