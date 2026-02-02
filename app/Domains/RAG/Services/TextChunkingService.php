<?php

namespace App\Domains\RAG\Services;

use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\DTOs\DocumentDTO;
use App\Domains\RAG\Factories\TokenizerFactory;
use App\Domains\RAG\Splitters\RecursiveTextSplitter;
use App\Domains\RAG\Tokenizers\TokenizerInterface;

/**
 * Service for chunking text into smaller pieces.
 *
 * Uses a hybrid tokenization strategy:
 * - Tiktoken (local) for the recursive splitting algorithm - fast, no API calls
 * - Gemini tokenizer (API) for final token count verification - 100% accurate
 *
 * Follows Single Responsibility Principle (SRP): Only text chunking logic.
 * Uses domain components (splitters, tokenizers) for chunking.
 * Follows Dependency Inversion Principle (DIP): Depends on factory interfaces.
 * Works with DTOs to keep domain logic separate from persistence.
 */
class TextChunkingService
{
    /**
     * Default chunk size in tokens.
     */
    private const DEFAULT_CHUNK_SIZE = 512;

    /**
     * Default overlap in tokens.
     */
    private const DEFAULT_OVERLAP = 100;

    /**
     * Minimum chunk size in tokens.
     */
    private const DEFAULT_MIN_CHUNK_SIZE = 50;

    public function __construct(
        private TokenizerFactory $tokenizerFactory
    ) {}

    /**
     * Chunk text into smaller pieces with overlap.
     *
     * Uses hybrid tokenization strategy:
     * 1. Tiktoken for splitting (local, ~85-90% accuracy, no API calls)
     * 2. Gemini tokenizer for final verification (API, 100% accurate)
     *
     * @param string $text The text to chunk
     * @param DocumentDTO $document The document DTO
     * @param array<string, mixed> $options Processing options (chunk_size, chunk_overlap, embedding_model, etc.)
     * @return array<ChunkDTO> Array of ChunkDTOs (without IDs, as they're not persisted yet)
     */
    public function chunkText(string $text, DocumentDTO $document, array $options = []): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // Get configuration from options or use defaults
        $chunkSizeTokens = $options['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE;
        $overlapTokens = $options['chunk_overlap'] ?? self::DEFAULT_OVERLAP;
        $minChunkSize = $options['min_chunk_size'] ?? self::DEFAULT_MIN_CHUNK_SIZE;
        $embeddingModel = $options['embedding_model'] ?? 'models/gemini-embedding-001';

        // Phase 1: Use tiktoken for splitting (local, fast, no API calls)
        // This dramatically improves performance during the recursive splitting algorithm
        $fastTokenizer = $this->tokenizerFactory->tiktoken();

        // Create splitter with fast tokenizer for speed
        $splitter = new RecursiveTextSplitter(
            tokenizer: $fastTokenizer,
            chunkSize: $chunkSizeTokens,
            chunkOverlap: $overlapTokens,
            minChunkSize: $minChunkSize,
            keepSeparator: false
        );

        // Split text into chunks (uses fast local tokenization)
        $chunkContents = $splitter->splitText($text);

        // Phase 2: Use Gemini tokenizer for final verification (API, 100% accurate)
        // This ensures the stored token counts are 100% accurate for billing/validation
        $accurateTokenizer = $this->tokenizerFactory->gemini($embeddingModel);

        // Create ChunkDTOs with verified token counts
        return $this->createChunkDTOs(
            $chunkContents,
            $document,
            $options,
            $accurateTokenizer,
            $chunkSizeTokens,
            $overlapTokens
        );
    }

    /**
     * Create ChunkDTOs from chunk content strings.
     *
     * Uses the accurate tokenizer to get exact token counts for each chunk.
     * Uses batch token counting for optimal performance (concurrent API calls).
     * These counts are used for billing and validation purposes.
     *
     * @param array<string> $chunkContents Array of chunk content strings
     * @param DocumentDTO $document The document DTO
     * @param array<string, mixed> $options Processing options
     * @param TokenizerInterface $accurateTokenizer Accurate tokenizer for final token counts
     * @param int $chunkSizeTokens Chunk size in tokens
     * @param int $overlapTokens Overlap in tokens
     * @return array<ChunkDTO> Array of ChunkDTOs
     */
    private function createChunkDTOs(
        array $chunkContents,
        DocumentDTO $document,
        array $options,
        TokenizerInterface $accurateTokenizer,
        int $chunkSizeTokens,
        int $overlapTokens
    ): array {
        if (empty($chunkContents)) {
            return [];
        }

        $totalChunks = count($chunkContents);

        // Build base metadata
        $baseMetadata = [
            'chunk_size' => $chunkSizeTokens,
            'overlap' => $overlapTokens,
            'total_chunks' => $totalChunks,
            'splitter' => 'RecursiveTextSplitter',
        ];

        // Merge with any additional metadata from options
        $chunkMetadata = array_merge($baseMetadata, $options['chunk_metadata'] ?? []);

        // Count tokens for all chunks in batch (concurrent API calls)
        // This reduces total time from N × latency to ~1 × latency
        $tokenCounts = $accurateTokenizer->countTokensBatch($chunkContents);

        $chunkDTOs = [];
        foreach ($chunkContents as $index => $content) {
            // Create ChunkDTO without ID (will be set after persistence)
            $chunkDTOs[] = new ChunkDTO(
                content: $content,
                index: $index,
                tokens: $tokenCounts[$index],
                id: 0, // Will be set after persistence
                documentId: $document->id ?? 0,
                versionId: $options['version_id'] ?? 0,
                companyId: $options['company_id'] ?? 0,
                metadata: $chunkMetadata,
                embedding: null
            );
        }

        return $chunkDTOs;
    }
}
