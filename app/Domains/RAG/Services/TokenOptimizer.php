<?php

namespace App\Domains\RAG\Services;

use App\Domains\RAG\Factories\TokenizerFactory;
use App\Services\V1\Common\LogService;
use App\Services\V1\Common\TokenCountCache;
use App\Services\V1\Common\TokenEstimator;

/**
 * Service for validating and optimizing token usage.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for
 * token counting, validation, and optimization strategies.
 */
class TokenOptimizer
{
    public function __construct(
        private readonly TokenizerFactory $tokenizerFactory,
        private readonly TokenCountCache $tokenCountCache,
        private readonly TokenEstimator $tokenEstimator,
        private readonly ?LogService $logService = null
    ) {}

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
    public function validateAndOptimize(
        array $messages,
        string $llmModel,
        int &$maxOutputTokens,
        int $maxTotalTokens,
        int $companyId
    ): array {
        try {
            // Get the actual tokenizer for the model
            $tokenizer = $this->tokenizerFactory->forModel($llmModel);

            // Count REAL tokens for each message (with caching)
            $totalInputTokens = $this->countTokens($messages, $tokenizer);

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

            return $this->fallbackOptimization($messages, $maxOutputTokens, $maxTotalTokens);
        }
    }

    /**
     * Count tokens for all messages.
     *
     * @param array<array<string, string>> $messages
     * @param mixed $tokenizer
     * @return int
     */
    private function countTokens(array $messages, $tokenizer): int
    {
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

        return $totalInputTokens;
    }

    /**
     * Fallback optimization using token estimation.
     *
     * @param array<array<string, string>> $messages
     * @param int $maxOutputTokens
     * @param int $maxTotalTokens
     * @return array<array<string, string>>
     */
    private function fallbackOptimization(array $messages, int &$maxOutputTokens, int $maxTotalTokens): array
    {
        // Use fast estimation as fallback
        $messageContents = array_filter(array_column($messages, 'content'));
        $estimatedTokens = $this->tokenEstimator->estimateMultiple($messageContents);
        $estimatedTokens += count($messages) * 4;

        if ($estimatedTokens + $maxOutputTokens > $maxTotalTokens) {
            $maxOutputTokens = max(500, $maxTotalTokens - $estimatedTokens);
        }

        return $messages;
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
        [$systemMessage, $userMessage, $contextMessages, $historyMessages] = $this->categorizeMessages($messages);

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
     * Categorize messages by type.
     *
     * @param array<array<string, string>> $messages
     * @return array{0: ?array, 1: ?array, 2: array, 3: array}
     */
    private function categorizeMessages(array $messages): array
    {
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

        return [$systemMessage, $userMessage, $contextMessages, $historyMessages];
    }
}
