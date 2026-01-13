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

        // Get tokenizer for the embedding model
        $tokenizer = $this->tokenizerFactory->create($embeddingModel);

        // Create splitter with proper configuration
        $splitter = new RecursiveTextSplitter(
            tokenizer: $tokenizer,
            chunkSize: $chunkSizeTokens,
            chunkOverlap: $overlapTokens,
            minChunkSize: $minChunkSize,
            keepSeparator: false
        );

        // Split text into chunks
        $chunkContents = $splitter->splitText($text);

        // Create ChunkDTOs from split chunks
        return $this->createChunkDTOs(
            $chunkContents,
            $document,
            $options,
            $tokenizer,
            $chunkSizeTokens,
            $overlapTokens
        );
    }

    /**
     * Create ChunkDTOs from chunk content strings.
     *
     * @param array<string> $chunkContents Array of chunk content strings
     * @param DocumentDTO $document The document DTO
     * @param array<string, mixed> $options Processing options
     * @param TokenizerInterface $tokenizer Tokenizer for counting tokens
     * @param int $chunkSizeTokens Chunk size in tokens
     * @param int $overlapTokens Overlap in tokens
     * @return array<ChunkDTO> Array of ChunkDTOs
     */
    private function createChunkDTOs(
        array $chunkContents,
        DocumentDTO $document,
        array $options,
        TokenizerInterface $tokenizer,
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

        $chunkDTOs = [];
        foreach ($chunkContents as $index => $content) {
            // Count tokens for this chunk
            $tokenCount = $tokenizer->countTokens($content);

            // Create ChunkDTO without ID (will be set after persistence)
            $chunkDTOs[] = new ChunkDTO(
                content: $content,
                index: $index,
                tokens: $tokenCount,
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
