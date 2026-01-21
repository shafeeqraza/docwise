<?php

namespace App\Domains\RAG\Contracts;

use App\Domains\RAG\DTOs\CompletionDTO;

/**
 * Contract for generating LLM completions.
 *
 * Follows Interface Segregation Principle (ISP): Only LLM completion-related methods.
 */
interface LLMProvider
{
    /**
     * Generate a completion from messages.
     *
     * @param array<array<string, string>> $messages Array of message arrays with 'role' and 'content' keys
     * @param array<string, mixed> $options Additional options (temperature, max_tokens, etc.)
     * @return CompletionDTO The completion DTO
     * @throws \RuntimeException If completion generation fails
     */
    public function generateCompletion(array $messages, array $options = []): CompletionDTO;

    /**
     * Check if this provider supports the given model.
     *
     * @param string $model The model name
     * @return bool True if the provider supports the model
     */
    public function supports(string $model): bool;
}
