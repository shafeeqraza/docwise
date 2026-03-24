<?php

namespace App\Domains\RAG\Pipelines;

use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\DTOs\ChatRagResult;
use App\Domains\RAG\DTOs\CompletionDTO;
use App\Domains\RAG\Factories\EmbeddingProviderFactory;
use App\Domains\RAG\Factories\LLMProviderFactory;
use App\Domains\RAG\Services\ChunkFilter;
use App\Domains\RAG\Services\TokenOptimizer;
use App\Domains\RAG\Services\VectorStoreService;
use App\Services\V1\Common\LogService;

/**
 * Chat RAG (Retrieval Augmented Generation) Pipeline.
 *
 * Orchestrates the complete RAG flow:
 * 1. Generate embedding for user query
 * 2. Search vector store for relevant context
 * 3. Build messages with context and history
 * 4. Validate and optimize tokens
 * 5. Generate LLM completion
 *
 * Follows Single Responsibility Principle (SRP): Only RAG orchestration logic.
 * Follows Dependency Inversion Principle (DIP): Depends on interfaces, not implementations.
 */
class ChatRAGPipeline
{
    public function __construct(
        private readonly VectorStoreService $vectorStoreService,
        private readonly EmbeddingProviderFactory $embeddingProviderFactory,
        private readonly LLMProviderFactory $llmProviderFactory,
        private readonly TokenOptimizer $tokenOptimizer,
        private readonly ChunkFilter $chunkFilter,
        private readonly ?LogService $logService = null
    ) {}

    /**
     * Execute the RAG pipeline.
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
     * @return ChatRagResult Pipeline result
     */
    public function execute(array $params): ChatRagResult
    {
        $startTime = microtime(true);

        // Extract parameters
        $message = $params['message'];
        $companyId = $params['company_id'];
        $chatHistory = $params['chat_history'] ?? [];
        $embeddingModel = $params['embedding_model'];
        $llmModel = $params['llm_model'];
        $maxContextChunks = $params['max_context_chunks'];
        $temperature = $params['temperature'];
        $maxOutputTokens = $params['max_output_tokens'];
        $maxTotalTokens = $params['max_total_tokens'];

        // Step 1: Generate embedding for user query
        $this->logService?->info('Generating query embedding', [
            'company_id' => $companyId,
            'model' => $embeddingModel,
        ]);

        $embeddingProvider = $this->embeddingProviderFactory->create($embeddingModel);
        $queryEmbedding = $embeddingProvider->generateEmbedding($message, $embeddingModel);

        // Step 2: Perform vector search with company filter
        $this->logService?->info('Searching vector store', [
            'company_id' => $companyId,
            'max_chunks' => $maxContextChunks,
        ]);

        $chunks = $this->vectorStoreService->search(
            $queryEmbedding->vector,
            $maxContextChunks,
            ['company_id' => $companyId]
        );

        // Step 2.5: Check if we have relevant chunks (similarity threshold)
        $relevantChunks = $this->chunkFilter->filterRelevant($chunks);

        if (empty($relevantChunks)) {
            $this->logService?->info('No relevant chunks found, returning no-info response', [
                'company_id' => $companyId,
                'total_chunks_retrieved' => count($chunks),
            ]);

            return new ChatRagResult(
                content: "I don't have any information about this topic in the provided documents. Please ask a question related to the content that has been uploaded to this knowledge base.",
                model: $llmModel,
                tokensPrompt: 0,
                tokensCompletion: 0,
                finishReason: 'no_relevant_context',
                retrievedChunks: [],
                citations: [],
                confidenceScore: 0.0,
            );
        }

        // Step 3: Build context from retrieved chunks
        $context = $this->buildContext($chunks);

        // Step 4: Build messages array
        $messages = $this->buildMessages($context, $chatHistory, $message);

        // Step 5: Validate and optimize tokens (CRITICAL - accurate counting)
        $messages = $this->tokenOptimizer->validateAndOptimize(
            $messages,
            $llmModel,
            $maxOutputTokens,
            $maxTotalTokens,
            $companyId
        );

        // Step 6: Call LLM provider to generate completion
        $this->logService?->info('Generating LLM completion', [
            'company_id' => $companyId,
            'model' => $llmModel,
            'max_output_tokens' => $maxOutputTokens,
        ]);

        $llmProvider = $this->llmProviderFactory->create($llmModel);
        $completion = $llmProvider->generateCompletion($messages, [
            'model' => $llmModel,
            'temperature' => $temperature,
            'max_tokens' => $maxOutputTokens,
        ]);

        // Step 7: Calculate latency
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // Step 8: Build citations and calculate confidence
        $citations = $this->buildCitations($relevantChunks);
        $confidenceScore = $this->calculateConfidenceScore($relevantChunks);

        $this->logService?->info('RAG pipeline completed', [
            'company_id' => $companyId,
            'latency_ms' => $latencyMs,
            'chunks_retrieved' => count($relevantChunks),
            'confidence_score' => $confidenceScore,
        ]);

        return new ChatRagResult(
            content: $completion->content,
            model: $completion->model,
            tokensPrompt: $completion->tokensPrompt,
            tokensCompletion: $completion->tokensCompletion,
            finishReason: $completion->finishReason,
            retrievedChunks: $relevantChunks,
            citations: $citations,
            confidenceScore: $confidenceScore,
        );
    }

    /**
     * Build context string from chunks.
     *
     * @param array<ChunkDTO> $chunks
     * @return string
     */
    private function buildContext(array $chunks): string
    {
        if (empty($chunks)) {
            return '';
        }

        $contextParts = [];
        foreach ($chunks as $index => $chunk) {
            $contextParts[] = "Context {$index}: {$chunk->content}";
        }

        return implode("\n\n", $contextParts);
    }

    /**
     * Build messages array for LLM.
     *
     * @param string $context Retrieved context
     * @param array $chatHistory Previous messages
     * @param string $userMessage Current user message
     * @param bool $hasRelevantContext Whether relevant context was found
     * @return array<array<string, string>>
     */
    private function buildMessages(string $context, array $chatHistory, string $userMessage, bool $hasRelevantContext = true): array
    {
        $messages = [];

        // System message with context
        $systemPrompt = "You are a helpful AI assistant that answers questions based ONLY on the provided context from uploaded documents.\n\n";

        if ($hasRelevantContext && !empty($context)) {
            $systemPrompt .= "IMPORTANT: You MUST only answer questions using the information provided in the context below. ";
            $systemPrompt .= "If the user's question cannot be answered using the provided context, you MUST respond with: ";
            $systemPrompt .= "\"I don't have any information about this topic in the provided documents. Please ask a question related to the content that has been uploaded to this knowledge base.\"\n\n";
            $systemPrompt .= "Do NOT use your general knowledge or make up answers. Only use information from the context.\n\n";
            $systemPrompt .= "Relevant Context:\n\n{$context}";
        } else {
            $systemPrompt .= "IMPORTANT: No relevant context was found for this query. ";
            $systemPrompt .= "You MUST respond with: ";
            $systemPrompt .= "\"I don't have any information about this topic in the provided documents. Please ask a question related to the content that has been uploaded to this knowledge base.\"";
            $systemPrompt .= "\n\nDo NOT attempt to answer the question using your general knowledge.";
        }

        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        // Add chat history
        foreach ($chatHistory as $historyItem) {
            $messages[] = [
                'role' => $historyItem['role'],
                'content' => $historyItem['content'],
            ];
        }

        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        return $messages;
    }


    /**
     * Build citations from chunks.
     *
     * @param array<ChunkDTO> $chunks
     * @return array
     */
    private function buildCitations(array $chunks): array
    {
        $citations = [];
        foreach ($chunks as $index => $chunk) {
            $citations[] = [
                'index' => $index + 1,
                'chunk_id' => $chunk->id,
                'document_id' => $chunk->documentId,
                'content_preview' => substr($chunk->content, 0, 150) . '...',
                'similarity_score' => $chunk->metadata['similarity_score'] ?? 0.0,
            ];
        }
        return $citations;
    }


    /**
     * Calculate confidence score from chunks.
     *
     * @param array<ChunkDTO> $chunks
     * @return float
     */
    private function calculateConfidenceScore(array $chunks): float
    {
        if (empty($chunks)) {
            return 0.0;
        }

        $scores = array_filter(array_map(function (ChunkDTO $chunk) {
            return $chunk->metadata['similarity_score'] ?? null;
        }, $chunks));

        if (empty($scores)) {
            return 0.5;
        }

        return (float) (array_sum($scores) / count($scores));
    }
}
