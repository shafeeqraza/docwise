<?php

namespace App\Domains\RAG\Splitters;

use App\Domains\RAG\Contracts\TextSplitter;
use App\Domains\RAG\Tokenizers\TokenizerInterface;

/**
 * Recursive text splitter that splits text into chunks using token-based boundaries.
 *
 * Follows Single Responsibility Principle (SRP): Only text splitting logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements TextSplitter interface.
 * Follows Dependency Inversion Principle (DIP): Depends on TokenizerInterface abstraction.
 */
class RecursiveTextSplitter implements TextSplitter
{
    /**
     * Default chunk size in tokens.
     */
    private int $chunkSize;

    /**
     * Overlap size in tokens.
     */
    private int $chunkOverlap;

    /**
     * List of separators to try in order (from largest to smallest semantic units).
     */
    private array $separators;

    /**
     * Minimum chunk size to keep (prevents very small chunks).
     */
    private int $minChunkSize;

    /**
     * Whether to keep separators in the chunks.
     */
    private bool $keepSeparator;

    /**
     * Tokenizer for token-based chunking (required).
     */
    private TokenizerInterface $tokenizer;

    /**
     * Handler for overlap logic.
     */
    private OverlapHandler $overlapHandler;

    /**
     * Handler for token-based splitting.
     */
    private TokenBasedSplitter $tokenBasedSplitter;

    /**
     * Create a new RecursiveTextSplitter instance.
     *
     * @param TokenizerInterface $tokenizer Tokenizer for token-based chunking (required)
     * @param int $chunkSize Maximum chunk size in tokens
     * @param int $chunkOverlap Overlap size in tokens
     * @param array|null $separators Custom separators (default: paragraphs, sentences, words, characters)
     * @param int $minChunkSize Minimum chunk size to keep (in tokens)
     * @param bool $keepSeparator Whether to keep separators in chunks
     */
    public function __construct(
        TokenizerInterface $tokenizer,
        int $chunkSize = 512,
        int $chunkOverlap = 100,
        ?array $separators = null,
        int $minChunkSize = 50,
        bool $keepSeparator = false
    ) {
        $this->chunkSize = $chunkSize;
        $this->chunkOverlap = $chunkOverlap;
        $this->separators = $separators ?? $this->getDefaultSeparators();
        $this->minChunkSize = $minChunkSize;
        $this->keepSeparator = $keepSeparator;

        // Use tokenizer directly - local tiktoken is fast enough without caching
        $this->tokenizer = $tokenizer;

        $this->overlapHandler = new OverlapHandler($this->tokenizer, $chunkSize, $chunkOverlap, $minChunkSize);
        $this->tokenBasedSplitter = new TokenBasedSplitter($this->tokenizer, $chunkSize);
    }

    /**
     * Split text into chunks recursively.
     *
     * @param string $text The text to split
     * @return array<string> Array of text chunks
     */
    public function splitText(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // If text is smaller than chunk size, return as single chunk
        if ($this->getLength($text) <= $this->chunkSize) {
            return [trim($text)];
        }

        // Split recursively using separators
        $chunks = $this->splitTextRecursive($text, $this->separators);

        // Apply overlap between chunks
        if ($this->chunkOverlap > 0 && count($chunks) > 1) {
            $chunks = $this->overlapHandler->mergeSplitsWithOverlap($chunks);
        }

        return $chunks;
    }

    /**
     * Get default separators in order of preference (largest to smallest).
     *
     * @return array
     */
    private function getDefaultSeparators(): array
    {
        return [
            "\n\n",      // Paragraphs (double newline)
            "\n",        // Lines (single newline)
            ". ",        // Sentences (period followed by space)
            "! ",        // Exclamation sentences
            "? ",        // Question sentences
            "; ",        // Semicolons
            ", ",        // Commas
            " ",         // Words (spaces)
            "",          // Characters (fallback - split by character)
        ];
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
     * Recursively split text using separators.
     *
     * @param string $text The text to split
     * @param array $separators List of separators to try
     * @return array Array of text chunks
     */
    private function splitTextRecursive(string $text, array $separators): array
    {
        // If no separators left, split by character
        if (empty($separators)) {
            return $this->splitByCharacter($text);
        }

        $separator = $separators[0];
        $remainingSeparators = array_slice($separators, 1);

        // Split by current separator
        $splits = $this->splitBySeparator($text, $separator);

        // If separator is empty (character split), use it directly
        if ($separator === '') {
            return $splits;
        }

        $finalChunks = [];
        $currentChunk = '';

        foreach ($splits as $split) {
            $splitLength = $this->getLength($split);

            // If current split is larger than chunk size, recursively split it
            if ($splitLength > $this->chunkSize) {
                // Save current chunk if it exists
                if (!empty(trim($currentChunk))) {
                    $finalChunks[] = trim($currentChunk);
                    $currentChunk = '';
                }

                // Recursively split the large split
                $recursiveChunks = $this->splitTextRecursive($split, $remainingSeparators);
                $finalChunks = array_merge($finalChunks, $recursiveChunks);
                continue;
            }

            // Check if adding this split would exceed chunk size
            $potentialChunk = empty($currentChunk) ? $split : $currentChunk . $separator . $split;
            $potentialLength = $this->getLength($potentialChunk);

            if ($potentialLength > $this->chunkSize && !empty(trim($currentChunk))) {
                $finalChunks[] = trim($currentChunk);
                $currentChunk = $split;
            } else {
                // Add to current chunk
                $currentChunk = $potentialChunk;
            }
        }

        // Add remaining chunk
        if (!empty(trim($currentChunk))) {
            $finalChunks[] = trim($currentChunk);
        }

        return $finalChunks;
    }

    /**
     * Split text by a specific separator.
     *
     * @param string $text The text to split
     * @param string $separator The separator to use
     * @return array Array of splits
     */
    private function splitBySeparator(string $text, string $separator): array
    {
        if ($separator === '') {
            // Split by character
            return $this->splitByCharacter($text);
        }

        $splits = explode($separator, $text);

        // If keeping separator, add it back (except for last split)
        if ($this->keepSeparator && $separator !== '') {
            $result = [];
            for ($i = 0; $i < count($splits) - 1; $i++) {
                $result[] = $splits[$i] . $separator;
            }
            $result[] = $splits[count($splits) - 1];
            return $result;
        }

        return $splits;
    }

    /**
     * Split text by character (fallback when no separators work).
     * Uses token-based splitting for accurate chunk sizing.
     *
     * @param string $text The text to split
     * @return array Array of token-based chunks
     */
    private function splitByCharacter(string $text): array
    {
        return $this->tokenBasedSplitter->splitByTokens($text);
    }


    /**
     * Set chunk size.
     *
     * @param int $chunkSize
     * @return self
     */
    public function setChunkSize(int $chunkSize): self
    {
        $this->chunkSize = $chunkSize;
        $this->overlapHandler->setChunkSize($chunkSize);
        $this->tokenBasedSplitter->setChunkSize($chunkSize);
        return $this;
    }

    /**
     * Set chunk overlap.
     *
     * @param int $chunkOverlap
     * @return self
     */
    public function setChunkOverlap(int $chunkOverlap): self
    {
        $this->chunkOverlap = $chunkOverlap;
        $this->overlapHandler->setChunkOverlap($chunkOverlap);
        return $this;
    }

    /**
     * Set minimum chunk size.
     *
     * @param int $minChunkSize
     * @return self
     */
    public function setMinChunkSize(int $minChunkSize): self
    {
        $this->minChunkSize = $minChunkSize;
        $this->overlapHandler->setMinChunkSize($minChunkSize);
        return $this;
    }

    /**
     * Set custom separators.
     *
     * @param array $separators
     * @return self
     */
    public function setSeparators(array $separators): self
    {
        $this->separators = $separators;
        return $this;
    }

    /**
     * Set tokenizer.
     *
     * @param TokenizerInterface $tokenizer Tokenizer instance (required)
     * @return self
     */
    public function setTokenizer(TokenizerInterface $tokenizer): self
    {
        $this->tokenizer = $tokenizer;
        $this->overlapHandler->setTokenizer($this->tokenizer);
        $this->tokenBasedSplitter->setTokenizer($this->tokenizer);
        return $this;
    }
}
