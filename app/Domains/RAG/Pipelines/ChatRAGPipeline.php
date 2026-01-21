<?php

namespace App\Domains\RAG\Pipelines;

use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\DTOs\CompletionDTO;
use App\Domains\RAG\Factories\EmbeddingProviderFactory;
use App\Domains\RAG\Factories\LLMProviderFactory;
use App\Domains\RAG\Factories\TokenizerFactory;
use App\Domains\RAG\Services\VectorStoreService;
use App\Services\V1\Common\LogService;
use App\Services\V1\Common\TokenCountCache;
use App\Services\V1\Common\TokenEstimator;

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
        private readonly TokenizerFactory $tokenizerFactory,
        private readonly TokenEstimator $tokenEstimator,
        private readonly TokenCountCache $tokenCountCache,
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
     * @return array{
     *     content: string,
     *     model: string,
     *     tokens_prompt: int,
     *     tokens_completion: int,
     *     finish_reason: string,
     *     retrieved_chunks: array<ChunkDTO>,
     *     citations: array,
     *     confidence_score: float
     * } Pipeline result
     */
    public function execute(array $params): array
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

        // Step 3: Build context from retrieved chunks
        $context = $this->buildContext($chunks);

        // Step 4: Build messages array
        $messages = $this->buildMessages($context, $chatHistory, $message);

        // Step 5: Validate and optimize tokens (CRITICAL - accurate counting)
        $messages = $this->validateAndOptimizeTokens(
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
        $citations = $this->buildCitations($chunks);
        $confidenceScore = $this->calculateConfidenceScore($chunks);

        $this->logService?->info('RAG pipeline completed', [
            'company_id' => $companyId,
            'latency_ms' => $latencyMs,
            'chunks_retrieved' => count($chunks),
            'confidence_score' => $confidenceScore,
        ]);

        return [
            'content' => $completion->content,
            'model' => $completion->model,
            'tokens_prompt' => $completion->tokensPrompt,
            'tokens_completion' => $completion->tokensCompletion,
            'finish_reason' => $completion->finishReason,
            'retrieved_chunks' => $chunks,
            'citations' => $citations,
            'confidence_score' => $confidenceScore,
        ];
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
     * @return array<array<string, string>>
     */
    private function buildMessages(string $context, array $chatHistory, string $userMessage): array
    {
        $messages = [];

        // System message with context
        $systemPrompt = "You are a helpful AI assistant. Use the following context to answer the user's question accurately.";
        if (!empty($context)) {
            $systemPrompt .= "\n\nRelevant Context:\n{$context}";
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
     * Validate and optimize token usage before LLM call.
     *
     * This is the CRITICAL validation point - we use real token counting here because:
     * 1. We've already invested time in embedding + vector search
     * 2. We're about to make an expensive LLM API call
     * 3. We need 100% accuracy for model context limits
     * 4. We can intelligently truncate context/history if needed
     *
     * @param array<array<string, string>> $messages The constructed messages
     * @param string $llmModel The LLM model being used
     * @param int $maxOutputTokens Max tokens for output (passed by reference)
     * @param int $maxTotalTokens Max total tokens allowed
     * @param int $companyId Company ID for logging
     * @return array<array<string, string>> Optimized messages array
     */
    private function validateAndOptimizeTokens(
        array $messages,
        string $llmModel,
        int &$maxOutputTokens,
        int $maxTotalTokens,
        int $companyId
    ): array {
        try {
            // Get the actual tokenizer for the model
            $tokenizer = $this->tokenizerFactory->create($llmModel);

            // Count REAL tokens for each message (with caching)
            $totalInputTokens = 0;
            foreach ($messages as $message) {
                if (isset($message['content'])) {
                    $tokens = $this->tokenCountCache->remember(
                        $message['content'],
                        fn() => $tokenizer->countTokens($message['content'])
                    );
                    $totalInputTokens += $tokens;
                }
                // Add overhead for message structure
                $totalInputTokens += 4;
            }

            $totalEstimatedTokens = $totalInputTokens + $maxOutputTokens;

            // Check if we exceed limits
            if ($totalEstimatedTokens > $maxTotalTokens) {
                $this->logService?->warning('Token limit exceeded, optimizing context', [
                    'total_input_tokens' => $totalInputTokens,
                    'requested_output_tokens' => $maxOutputTokens,
                    'total_estimated' => $totalEstimatedTokens,
                    'max_total' => $maxTotalTokens,
                    'company_id' => $companyId,
                    'model' => $llmModel,
                ]);

                // Strategy: Reduce output tokens first, then truncate context if needed
                $availableForOutput = $maxTotalTokens - $totalInputTokens;

                if ($availableForOutput < 500) {
                    // Not enough space - need to truncate context
                    $messages = $this->truncateContext($messages, $tokenizer, $maxTotalTokens, $maxOutputTokens);
                } else {
                    // Just reduce output tokens
                    $maxOutputTokens = max(500, min($maxOutputTokens, $availableForOutput));

                    $this->logService?->info('Reduced output tokens to fit budget', [
                        'new_max_output_tokens' => $maxOutputTokens,
                        'company_id' => $companyId,
                    ]);
                }
            } else {
                $this->logService?->info('Token validation passed', [
                    'total_input_tokens' => $totalInputTokens,
                    'max_output_tokens' => $maxOutputTokens,
                    'total_budget' => $maxTotalTokens,
                    'headroom' => $maxTotalTokens - $totalEstimatedTokens,
                    'company_id' => $companyId,
                ]);
            }

            return $messages;
        } catch (\Exception $e) {
            // If real token counting fails, fall back to estimation
            $this->logService?->warning('Token validation failed, using estimation fallback', [
                'error' => $e->getMessage(),
                'company_id' => $companyId,
            ]);

            // Use fast estimation as fallback
            $messageContents = array_filter(array_column($messages, 'content'));
            $estimatedTokens = $this->tokenEstimator->estimateMultiple($messageContents);
            $estimatedTokens += count($messages) * 4;

            if ($estimatedTokens + $maxOutputTokens > $maxTotalTokens) {
                $maxOutputTokens = max(500, $maxTotalTokens - $estimatedTokens);
            }

            return $messages;
        }
    }

    /**
     * Truncate context intelligently to fit within token budget.
     *
     * Strategy:
     * 1. Keep system message (required)
     * 2. Keep user's current message (required)
     * 3. Reduce chat history (oldest first)
     * 4. Reduce retrieved context (lowest similarity first)
     *
     * @param array<array<string, string>> $messages The messages array
     * @param mixed $tokenizer The tokenizer instance
     * @param int $maxTotalTokens Maximum total tokens
     * @param int $targetOutputTokens Target output tokens
     * @return array<array<string, string>> Truncated messages
     */
    private function truncateContext(
        array $messages,
        $tokenizer,
        int $maxTotalTokens,
        int $targetOutputTokens
    ): array {
        $targetInputTokens = $maxTotalTokens - $targetOutputTokens;

        // Identify message types
        $systemMessage = null;
        $userMessage = null;
        $contextMessages = [];
        $historyMessages = [];

        foreach ($messages as $index => $message) {
            $role = $message['role'] ?? '';

            if ($role === 'system') {
                $systemMessage = ['index' => $index, 'message' => $message];
            } elseif ($role === 'user' && $index === count($messages) - 1) {
                // Last message is current user query
                $userMessage = ['index' => $index, 'message' => $message];
            } elseif (isset($message['name']) && $message['name'] === 'context') {
                $contextMessages[] = ['index' => $index, 'message' => $message];
            } else {
                $historyMessages[] = ['index' => $index, 'message' => $message];
            }
        }

        // Build optimized messages array
        $optimizedMessages = [];
        $currentTokens = 0;

        // 1. Always include system message
        if ($systemMessage) {
            $optimizedMessages[] = $systemMessage['message'];
            $currentTokens += $this->tokenCountCache->remember(
                $systemMessage['message']['content'],
                fn() => $tokenizer->countTokens($systemMessage['message']['content'])
            ) + 4;
        }

        // 2. Calculate user message tokens
        if ($userMessage) {
            $userTokens = $this->tokenCountCache->remember(
                $userMessage['message']['content'],
                fn() => $tokenizer->countTokens($userMessage['message']['content'])
            ) + 4;
            $currentTokens += $userTokens;
        }

        // 3. Add context chunks (reduce if needed)
        $availableTokens = $targetInputTokens - $currentTokens;
        foreach ($contextMessages as $contextMsg) {
            $msgTokens = $this->tokenCountCache->remember(
                $contextMsg['message']['content'],
                fn() => $tokenizer->countTokens($contextMsg['message']['content'])
            ) + 4;

            if ($currentTokens + $msgTokens <= $availableTokens) {
                $optimizedMessages[] = $contextMsg['message'];
                $currentTokens += $msgTokens;
            } else {
                break; // Stop adding context
            }
        }

        // 4. Add history (newest first, oldest dropped)
        $availableTokens = $targetInputTokens - $currentTokens;
        foreach (array_reverse($historyMessages) as $historyMsg) {
            $msgTokens = $this->tokenCountCache->remember(
                $historyMsg['message']['content'],
                fn() => $tokenizer->countTokens($historyMsg['message']['content'])
            ) + 4;

            if ($currentTokens + $msgTokens <= $availableTokens) {
                array_splice($optimizedMessages, count($optimizedMessages) - 1, 0, [$historyMsg['message']]);
                $currentTokens += $msgTokens;
            } else {
                break; // Stop adding history
            }
        }

        // 5. Add user message at the end
        if ($userMessage) {
            $optimizedMessages[] = $userMessage['message'];
        }

        $this->logService?->info('Context truncated to fit token budget', [
            'original_messages' => count($messages),
            'optimized_messages' => count($optimizedMessages),
            'final_input_tokens' => $currentTokens,
            'target_input_tokens' => $targetInputTokens,
        ]);

        return $optimizedMessages;
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
