<?php

namespace App\Services\V1\Chat\RAG;

use App\Models\ChatSession;
use App\Services\V1\DTOs\SendChatMessageDTO;

/**
 * Factory for creating RAG pipeline request parameters.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for merging
 * DTO values with config defaults to create pipeline parameters.
 */
class ChatRagRequestFactory
{
    /**
     * Create pipeline parameters from DTO and session.
     *
     * @param SendChatMessageDTO $dto
     * @param ChatSession $session
     * @return array{
     *     message: string,
     *     company_id: int,
     *     chat_history: array,
     *     embedding_model: string,
     *     llm_model: string,
     *     max_context_chunks: int,
     *     temperature: float,
     *     max_output_tokens: int,
     *     max_total_tokens: int
     * }
     */
    public function createPipelineParams(
        SendChatMessageDTO $dto,
        ChatSession $session,
        array $chatHistory
    ): array {
        return [
            'message' => $dto->message,
            'company_id' => $session->company_id,
            'chat_history' => $chatHistory,
            'embedding_model' => $dto->embeddingModel ?? config('chat.default_embedding_model', 'models/gemini-embedding-001'),
            'llm_model' => $dto->llmModel ?? config('chat.default_llm_model', 'Gemini 2.5 Flash'),
            'max_context_chunks' => $dto->maxContextChunks ?? config('chat.max_context_chunks', 5),
            'temperature' => $dto->temperature ?? config('chat.temperature', 0.7),
            'max_output_tokens' => $dto->maxTokens ?? config('chat.max_output_tokens', 1000),
            'max_total_tokens' => config('chat.max_total_tokens', 8000),
        ];
    }
}
