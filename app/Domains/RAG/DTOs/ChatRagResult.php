<?php

namespace App\Domains\RAG\DTOs;

/**
 * Typed result object for RAG pipeline execution.
 *
 * Follows Single Responsibility Principle (SRP): Only data transfer, no behavior.
 * Immutable: Properties are readonly.
 */
final readonly class ChatRagResult
{
    /**
     * @param string $content Generated response content
     * @param string $model Model used for generation
     * @param int $tokensPrompt Tokens used in prompt
     * @param int $tokensCompletion Tokens used in completion
     * @param string $finishReason Reason for completion finish
     * @param array<ChunkDTO> $retrievedChunks Chunks retrieved from vector store
     * @param array $citations Citations array
     * @param float $confidenceScore Confidence score (0.0-1.0)
     */
    public function __construct(
        public string $content,
        public string $model,
        public int $tokensPrompt,
        public int $tokensCompletion,
        public string $finishReason,
        public array $retrievedChunks,
        public array $citations,
        public float $confidenceScore,
    ) {}

    /**
     * Convert to array format (for backward compatibility if needed).
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'model' => $this->model,
            'tokens_prompt' => $this->tokensPrompt,
            'tokens_completion' => $this->tokensCompletion,
            'finish_reason' => $this->finishReason,
            'retrieved_chunks' => $this->retrievedChunks,
            'citations' => $this->citations,
            'confidence_score' => $this->confidenceScore,
        ];
    }
}
