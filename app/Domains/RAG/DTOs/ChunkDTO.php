<?php

namespace App\Domains\RAG\DTOs;

/**
 * Data Transfer Object for text chunks.
 *
 * Follows Single Responsibility Principle (SRP): Only data transfer, no behavior.
 * Immutable: Properties are readonly.
 */
readonly class ChunkDTO
{
    /**
     * @param string $content Chunk text content
     * @param int $index Chunk index/position
     * @param array<string, mixed> $metadata Additional metadata
     * @param int $tokens Token count
     * @param EmbeddingDTO|null $embedding Embedding DTO
     * @param int $id Chunk ID (if persisted)
     * @param int $documentId Document ID
     * @param int $versionId Version ID
     * @param int $companyId Company ID
     */
    public function __construct(
        public string $content,
        public int $index,
        public int $tokens,
        public int $id,
        public int $documentId,
        public int $versionId,
        public int $companyId,
        public array $metadata = [],
        public ?EmbeddingDTO $embedding = null,
    ) {
        $this->validate();
    }

    /**
     * Validate the DTO data.
     *
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    private function validate(): void
    {
        if (empty(trim($this->content))) {
            throw new \InvalidArgumentException('Chunk content cannot be empty');
        }

        if ($this->index < 0) {
            throw new \InvalidArgumentException('Chunk index must be non-negative');
        }

        if ($this->tokens !== null && $this->tokens < 0) {
            throw new \InvalidArgumentException('Token count must be non-negative');
        }

        // EmbeddingDTO validation is handled by the DTO itself
    }

    /**
     * Create a new ChunkDTO with updated embedding.
     *
     * @param EmbeddingDTO $embedding The embedding DTO
     * @return ChunkDTO New instance with updated embedding
     */
    public function withEmbedding(EmbeddingDTO $embedding): self
    {
        return new self(
            $this->content,
            $this->index,
            $this->tokens,
            $this->id,
            $this->documentId,
            $this->versionId,
            $this->companyId,
            $this->metadata,
            $embedding,
        );
    }

    /**
     * Create a new ChunkDTO with updated tokens.
     *
     * @param int $tokens Token count
     * @return ChunkDTO New instance with updated tokens
     */
    public function withTokens(int $tokens): self
    {
        return new self(
            $this->content,
            $this->index,
            $tokens,
            $this->id,
            $this->documentId,
            $this->versionId,
            $this->companyId,
            $this->metadata,
            $this->embedding,

        );
    }

    /**
     * Create a new ChunkDTO with updated ID.
     *
     * @param int $id Chunk ID
     * @return ChunkDTO New instance with updated ID
     */
    public function withId(int $id): self
    {
        return new self(
            $this->content,
            $this->index,
            $this->tokens,
            $id,
            $this->documentId,
            $this->versionId,
            $this->companyId,
            $this->metadata,
            $this->embedding,
        );
    }

    /**
     * Create a new ChunkDTO with updated metadata.
     *
     * @param array<string, mixed> $metadata Updated metadata
     * @return ChunkDTO New instance with updated metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->content,
            $this->index,
            $this->tokens,
            $this->id,
            $this->documentId,
            $this->versionId,
            $this->companyId,
            $metadata,
            $this->embedding,
        );
    }
}
