<?php

namespace App\Domains\RAG\DTOs;

/**
 * Data Transfer Object for embeddings.
 *
 * Follows Single Responsibility Principle (SRP): Only data transfer, no behavior.
 * Immutable: Properties are readonly.
 */
final readonly class EmbeddingDTO
{
    /**
     * @param array<int> $vector The embedding vector
     * @param string $model The embedding model name
     * @param int $dimension The dimension of the embedding vector
     */
    public function __construct(
        public array $vector,
        public string $model,
        public int $dimension
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
        if (empty($this->vector)) {
            throw new \InvalidArgumentException('Embedding vector cannot be empty');
        }

        if (empty(trim($this->model))) {
            throw new \InvalidArgumentException('Embedding model cannot be empty');
        }

        if ($this->dimension <= 0) {
            throw new \InvalidArgumentException('Embedding dimension must be positive');
        }

        if (count($this->vector) !== $this->dimension) {
            throw new \InvalidArgumentException(
                "Vector dimension mismatch: expected {$this->dimension}, got " . count($this->vector)
            );
        }
    }
}
