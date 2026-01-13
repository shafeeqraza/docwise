<?php

namespace App\Domains\RAG\Splitters;

use App\Domains\RAG\Tokenizers\TokenizerInterface;

/**
 * Handles overlap logic for text chunks.
 *
 * Follows Single Responsibility Principle (SRP): Only overlap handling logic.
 */
class OverlapHandler
{
    /**
     * Create a new OverlapHandler instance.
     *
     * @param TokenizerInterface $tokenizer Tokenizer for token-based operations
     * @param int $chunkSize Maximum chunk size in tokens
     * @param int $chunkOverlap Overlap size in tokens
     * @param int $minChunkSize Minimum chunk size to keep (in tokens)
     */
    public function __construct(
        private TokenizerInterface $tokenizer,
        private int $chunkSize,
        private int $chunkOverlap,
        private int $minChunkSize
    ) {}

    /**
     * Merge splits with overlap between chunks.
     *
     * @param array $splits Array of text splits
     * @return array Array of chunks with overlap
     */
    public function mergeSplitsWithOverlap(array $splits): array
    {
        if (count($splits) <= 1) {
            return $splits;
        }

        $chunks = [];
        $currentChunk = '';

        foreach ($splits as $split) {
            $split = trim($split);

            if (empty($split)) {
                continue;
            }

            // If this is the first split, start building the first chunk
            if (empty($currentChunk)) {
                $currentChunk = $split;
                continue;
            }

            // Process the split with overlap
            $result = $this->processSplitWithOverlap($currentChunk, $split);

            if ($result['finalize']) {
                $chunks[] = $result['previousChunk'];
                $currentChunk = $result['nextChunk'];
            } else {
                $currentChunk = $result['mergedChunk'];
            }
        }

        // Add the last chunk if it's not empty
        if (!empty(trim($currentChunk))) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * Process a split with overlap logic.
     *
     * @param string $currentChunk The current chunk being built
     * @param string $split The next split to add
     * @return array Result with 'finalize', 'previousChunk', 'nextChunk', and 'mergedChunk' keys
     */
    private function processSplitWithOverlap(string $currentChunk, string $split): array
    {
        // Detect separator between chunks (space is most common for space-separated text)
        $separator = $this->detectSeparator($currentChunk, $split);

        // Calculate overlap from the end of current chunk
        // This overlap text is ALREADY part of currentChunk (it's the last N tokens/chars)
        $overlapText = $this->getOverlapText($currentChunk, $this->chunkOverlap);

        // Check if we can add the split directly (with proper separator)
        $potentialChunk = $currentChunk . $separator . $split;
        $potentialLength = $this->getLength($potentialChunk);

        // If adding this split would exceed chunk size, finalize current chunk
        if ($potentialLength > $this->chunkSize) {
            // Current chunk is finalized as-is
            // It already ends with the overlap text (which is part of the chunk)
            $finalizedChunk = $currentChunk;

            // Start new chunk with overlap from previous chunk + separator + the new split
            // This ensures continuity: overlap appears at end of previous and start of next
            // Trim any trailing space from overlapText before adding separator
            $overlapTextTrimmed = rtrim($overlapText, ' ');
            $nextChunk = $overlapTextTrimmed . $separator . $split;

            // If the new chunk is still too large, trim the split to fit
            // This preserves content by keeping overlap and trimming from the split
            if ($this->getLength($nextChunk) > $this->chunkSize) {
                $overlapLength = $this->getLength($overlapTextTrimmed . $separator);
                $availableTokens = $this->chunkSize - $overlapLength;

                if ($availableTokens > $this->minChunkSize) {
                    // Trim the split to fit within available tokens
                    $trimmedSplit = $this->trimToTokenLimit($split, $availableTokens);
                    $nextChunk = $overlapTextTrimmed . $separator . $trimmedSplit;
                } else {
                    // If overlap itself is too large, just use the split (shouldn't happen normally)
                    $nextChunk = $split;
                }
            }

            return [
                'finalize' => true,
                'previousChunk' => $finalizedChunk,
                'nextChunk' => $nextChunk,
                'mergedChunk' => '',
            ];
        }

        // Merge: add split directly to current chunk with separator
        return [
            'finalize' => false,
            'previousChunk' => '',
            'nextChunk' => '',
            'mergedChunk' => $potentialChunk,
        ];
    }

    /**
     * Detect the appropriate separator between two text chunks.
     *
     * @param string $chunk1 First chunk
     * @param string $chunk2 Second chunk
     * @return string The separator to use
     */
    private function detectSeparator(string $chunk1, string $chunk2): string
    {
        // Check if chunks already have separators at boundaries
        $chunk1End = mb_substr($chunk1, -1);
        $chunk2Start = mb_substr($chunk2, 0, 1);

        // If chunk1 ends with space or chunk2 starts with space, no extra separator needed
        if ($chunk1End === ' ' || $chunk2Start === ' ') {
            return '';
        }

        // Check for other common separators
        $commonSeparators = ["\n\n", "\n", ". ", "! ", "? ", "; ", ", "];
        foreach ($commonSeparators as $sep) {
            if (mb_substr($chunk1, -mb_strlen($sep)) === $sep || mb_substr($chunk2, 0, mb_strlen($sep)) === $sep) {
                return '';
            }
        }

        // Default to space separator for space-separated content (like numbers)
        return ' ';
    }

    /**
     * Trim text to fit within a token limit while preserving word boundaries.
     *
     * @param string $text The text to trim
     * @param int $maxTokens Maximum tokens allowed
     * @return string Trimmed text that fits within token limit
     */
    private function trimToTokenLimit(string $text, int $maxTokens): string
    {
        if ($this->getLength($text) <= $maxTokens) {
            return $text;
        }

        // Use binary search to find the optimal trim point
        $textLength = mb_strlen($text);
        $low = 0;
        $high = $textLength;
        $bestTrim = '';

        while ($low < $high) {
            $mid = (int) (($low + $high) / 2);
            $trimmed = mb_substr($text, 0, $mid);
            $tokenCount = $this->getLength($trimmed);

            if ($tokenCount <= $maxTokens) {
                $bestTrim = $trimmed;
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        // Try to break at word boundary
        if (!empty($bestTrim)) {
            $lastSpace = mb_strrpos($bestTrim, ' ');
            if ($lastSpace !== false && $lastSpace > mb_strlen($bestTrim) * 0.7) {
                $bestTrim = mb_substr($bestTrim, 0, $lastSpace);
            }
        }

        return $bestTrim ?: mb_substr($text, 0, min($maxTokens * 3, $textLength)); // Fallback
    }

    /**
     * Get overlap text from the end of a chunk.
     *
     * @param string $text The text to get overlap from
     * @param int $overlapSize Size of overlap in tokens
     * @return string Overlap text
     */
    private function getOverlapText(string $text, int $overlapSize): string
    {
        $textLength = $this->getLength($text);

        if ($textLength <= $overlapSize) {
            return $text . ' ';
        }

        return $this->getTokenBasedOverlap($text, $overlapSize);
    }

    /**
     * Get token-based overlap text using binary search.
     *
     * @param string $text The text to get overlap from
     * @param int $overlapSize Size of overlap in tokens
     * @return string Overlap text
     */
    private function getTokenBasedOverlap(string $text, int $overlapSize): string
    {
        $textCharLength = mb_strlen($text);
        $bestOverlap = $this->findOptimalOverlapByTokens($text, $textCharLength, $overlapSize);

        // If no overlap found (rare edge case), use character substring as last resort
        // This should rarely happen, but ensures we always return something
        // Note: This is NOT using CharacterTokenizer - just a simple substring fallback
        if (empty($bestOverlap)) {
            // Estimate character length for overlap (rough approximation: ~3.5 chars per token)
            $overlapChars = (int) ($overlapSize * 3.5);
            $overlap = mb_substr($text, $textCharLength - min($overlapChars, $textCharLength));
            return $this->adjustOverlapAtWordBoundary($overlap);
        }

        return $this->adjustOverlapAtWordBoundary($bestOverlap);
    }

    /**
     * Find optimal overlap using binary search on token count.
     *
     * @param string $text The text to search
     * @param int $textCharLength Character length of text
     * @param int $overlapSize Target overlap size in tokens
     * @return string Best overlap text found
     */
    private function findOptimalOverlapByTokens(string $text, int $textCharLength, int $overlapSize): string
    {
        $low = 0;
        $high = $textCharLength;
        $bestOverlap = '';

        while ($low < $high) {
            $mid = (int) (($low + $high) / 2);
            $overlap = mb_substr($text, $textCharLength - $mid);
            $tokenCount = $this->tokenizer->getTokenLength($overlap);

            if ($tokenCount <= $overlapSize) {
                $bestOverlap = $overlap;
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $bestOverlap;
    }

    /**
     * Adjust overlap text to break at word boundary if possible.
     * Ensures we don't cut in the middle of words/numbers.
     *
     * @param string $overlap The overlap text to adjust
     * @return string Adjusted overlap text (preserves word boundaries)
     */
    private function adjustOverlapAtWordBoundary(string $overlap): string
    {
        // If overlap starts with a space, remove it (we'll add it back if needed)
        $overlap = ltrim($overlap, ' ');

        // Find the first space to ensure we start at a word boundary
        // This ensures we don't cut in the middle of a word/number
        $firstSpacePos = mb_strpos($overlap, ' ');

        if ($firstSpacePos !== false && $firstSpacePos > 0) {
            // Start from after the first space to get complete words
            $overlap = mb_substr($overlap, $firstSpacePos + 1);
        }

        // Ensure overlap ends with a space for proper separation
        $overlap = rtrim($overlap, ' ');
        return $overlap . ' ';
    }

    /**
     * Get length of text in tokens.
     *
     * @param string $text The text to measure
     * @return int Length in tokens
     */
    private function getLength(string $text): int
    {
        return $this->tokenizer->getTokenLength($text);
    }

    /**
     * Update chunk size.
     *
     * @param int $chunkSize
     * @return self
     */
    public function setChunkSize(int $chunkSize): self
    {
        $this->chunkSize = $chunkSize;
        return $this;
    }

    /**
     * Update chunk overlap.
     *
     * @param int $chunkOverlap
     * @return self
     */
    public function setChunkOverlap(int $chunkOverlap): self
    {
        $this->chunkOverlap = $chunkOverlap;
        return $this;
    }

    /**
     * Update minimum chunk size.
     *
     * @param int $minChunkSize
     * @return self
     */
    public function setMinChunkSize(int $minChunkSize): self
    {
        $this->minChunkSize = $minChunkSize;
        return $this;
    }

    /**
     * Update tokenizer.
     *
     * @param TokenizerInterface $tokenizer
     * @return self
     */
    public function setTokenizer(TokenizerInterface $tokenizer): self
    {
        $this->tokenizer = $tokenizer;
        return $this;
    }
}
