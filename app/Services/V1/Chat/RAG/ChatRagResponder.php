<?php

namespace App\Services\V1\Chat\RAG;

use App\Domains\RAG\DTOs\ChatRagResult;
use App\Domains\RAG\Pipelines\ChatRAGPipeline;

/**
 * Service for executing RAG pipeline and returning typed result.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for
 * executing RAG pipeline (now just a pass-through since pipeline returns typed result).
 */
class ChatRagResponder
{
    public function __construct(
        private readonly ChatRAGPipeline $ragPipeline
    ) {}

    /**
     * Execute RAG pipeline and return typed result.
     *
     * @param array{
     *     message: string,
     *     company_id: int,
     *     chat_history: array,
     *     embedding_model: string,
     *     llm_model: string,
     *     max_context_chunks: int,
     *     temperature: float,
     *     max_output_tokens: int,
     *     max_total_tokens: int
     * } $params Pipeline parameters
     * @return ChatRagResult Typed result
     */
    public function execute(array $params): ChatRagResult
    {
        return $this->ragPipeline->execute($params);
    }
}
