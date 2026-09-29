<?php

namespace App\Domains\RAG\DTOs;

/**
 * Data Transfer Object for LLM completion responses.
 *
 * Follows Single Responsibility Principle (SRP): Only data transfer, no behavior.
 * Immutable: Properties are readonly.
 */
final readonly class CompletionDTO
{
    /**
     * @param string $content The generated text content
     * @param string $model The model used for generation
     * @param int $tokensPrompt Number of tokens in the prompt
     * @param int $tokensCompletion Number of tokens in the completion
     * @param string $finishReason Reason for completion (e.g., 'stop', 'length', 'content_filter')
     * @param array<string, mixed> $rawResponse Raw response from the LLM API
     */
    public function __construct(
        public string $content,
        public string $model,
        public int $tokensPrompt,
        public int $tokensCompletion,
        public string $finishReason,
        public array $rawResponse = []
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
        if (empty(trim($this->model))) {
            throw new \InvalidArgumentException('Model cannot be empty');
        }

        if ($this->tokensPrompt < 0) {
            throw new \InvalidArgumentException('Tokens prompt must be non-negative');
        }

        if ($this->tokensCompletion < 0) {
            throw new \InvalidArgumentException('Tokens completion must be non-negative');
        }

        if (empty(trim($this->finishReason))) {
            throw new \InvalidArgumentException('Finish reason cannot be empty');
        }
    }

    /**
     * Get total tokens used.
     *
     * @return int Total tokens (prompt + completion)
     */
    public function getTotalTokens(): int
    {
        return $this->tokensPrompt + $this->tokensCompletion;
    }
}
