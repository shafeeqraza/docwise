<?php

namespace App\Domains\RAG\Services;

use App\Domains\RAG\DTOs\ChunkDTO;
use App\Models\UsageMetric;

/**
 * Service for tracking usage metrics and cost analytics.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for usage metric tracking.
 */
class UsageMetricService
{
    /**
     * Pricing per 1M tokens for different embedding models.
     * Source: Provider pricing pages (as of implementation date).
     */
    private const EMBEDDING_PRICING = [
        // OpenAI models (per 1M tokens)
        'text-embedding-3-small' => 0.02,
        'text-embedding-3-large' => 0.13,
        'text-embedding-ada-002' => 0.10,

        // Gemini models (per 1M tokens)
        'models/gemini-embedding-001' => 0.00, // Free tier / batch pricing
        'models/text-embedding-004' => 0.00,
    ];

    /**
     * Record document processing metrics with cost tracking.
     *
     * @param int $companyId Company ID
     * @param array<ChunkDTO> $chunks Array of processed chunks
     * @return void
     */
    public function recordDocumentProcessing(int $companyId, array $chunks): void
    {
        if (empty($chunks)) {
            return;
        }

        $chunkCount = count($chunks);
        $totalTokens = array_sum(array_map(fn($chunk) => $chunk->tokens, $chunks));
        $embeddingsGenerated = count(array_filter($chunks, fn($chunk) => $chunk->embedding !== null));

        // Calculate estimated costs
        $embeddingModel = $chunks[0]->embedding?->model ?? 'models/gemini-embedding-001';
        $estimatedCost = $this->calculateEmbeddingCost($totalTokens, $embeddingModel);

        // Update daily metrics
        $this->updateDailyMetrics($companyId, [
            'documents_processed' => 1,
            'chunks_created' => $chunkCount,
            'embeddings_generated' => $embeddingsGenerated,
            'tokens_prompt' => $totalTokens, // Tokens used for document processing/embedding
        ], [
            'embedding' => $estimatedCost,
        ]);

        // Update monthly metrics
        $this->updateMonthlyMetrics($companyId, [
            'documents_processed' => 1,
            'chunks_created' => $chunkCount,
            'embeddings_generated' => $embeddingsGenerated,
            'tokens_prompt' => $totalTokens,
        ], [
            'embedding' => $estimatedCost,
        ]);
    }

    /**
     * Record chat/query usage metrics with cost tracking.
     *
     * @param int $companyId Company ID
     * @param int $promptTokens Tokens used in prompt
     * @param int $completionTokens Tokens used in completion
     * @return void
     */
    public function recordChatUsage(int $companyId, int $promptTokens, int $completionTokens): void
    {
        $estimatedCost = $this->calculateLLMCost($promptTokens, $completionTokens);

        // Update daily metrics
        $this->updateDailyMetrics($companyId, [
            'chat_messages' => 1,
            'tokens_prompt' => $promptTokens,
            'tokens_completion' => $completionTokens,
            'vector_queries' => 1, // Assume each chat triggers a vector search
        ], [
            'llm' => $estimatedCost,
        ]);

        // Update monthly metrics
        $this->updateMonthlyMetrics($companyId, [
            'chat_messages' => 1,
            'tokens_prompt' => $promptTokens,
            'tokens_completion' => $completionTokens,
            'vector_queries' => 1,
        ], [
            'llm' => $estimatedCost,
        ]);
    }

    /**
     * Calculate embedding cost based on tokens and model.
     *
     * @param int $tokens Number of tokens
     * @param string $model Embedding model
     * @return float Cost in USD
     */
    private function calculateEmbeddingCost(int $tokens, string $model): float
    {
        $pricePerMillionTokens = self::EMBEDDING_PRICING[$model] ?? 0.0;
        return ($tokens / 1_000_000) * $pricePerMillionTokens;
    }

    /**
     * Calculate LLM cost based on tokens.
     * This is a simplified calculation - actual pricing varies by model.
     *
     * @param int $promptTokens Prompt tokens
     * @param int $completionTokens Completion tokens
     * @return float Cost in USD
     */
    private function calculateLLMCost(int $promptTokens, int $completionTokens): float
    {
        // Simplified pricing - should be configured per model in production
        // Example: GPT-4 pricing (per 1M tokens)
        $promptPrice = 0.03; // $0.03 per 1M prompt tokens
        $completionPrice = 0.06; // $0.06 per 1M completion tokens

        $promptCost = ($promptTokens / 1_000_000) * $promptPrice;
        $completionCost = ($completionTokens / 1_000_000) * $completionPrice;

        return $promptCost + $completionCost;
    }

    /**
     * Update daily usage metrics.
     *
     * @param int $companyId Company ID
     * @param array<string, int> $increments Metrics to increment
     * @param array<string, float> $costs Costs to add (by type)
     * @return void
     */
    private function updateDailyMetrics(int $companyId, array $increments, array $costs = []): void
    {
        $periodStart = now()->startOfDay();
        $periodEnd = now()->endOfDay();

        // Get or create the record
        $metric = UsageMetric::updateOrCreate(
            [
                'company_id' => $companyId,
                'period_start' => $periodStart,
                'metric_type' => 'daily',
            ],
            [
                'period_end' => $periodEnd,
            ]
        );

        // Increment metrics atomically (each increment is a separate query for atomicity)
        foreach ($increments as $field => $value) {
            UsageMetric::where('company_id', $companyId)
                ->where('period_start', $periodStart)
                ->where('metric_type', 'daily')
                ->increment($field, $value);
        }

        // Update costs (merge with existing costs)
        if (!empty($costs)) {
            $existingCosts = $metric->costs ?? [];
            foreach ($costs as $type => $amount) {
                $existingCosts[$type] = ($existingCosts[$type] ?? 0.0) + $amount;
            }
            $metric->costs = $existingCosts;
            $metric->save();
        }
    }

    /**
     * Update monthly usage metrics.
     *
     * @param int $companyId Company ID
     * @param array<string, int> $increments Metrics to increment
     * @param array<string, float> $costs Costs to add (by type)
     * @return void
     */
    private function updateMonthlyMetrics(int $companyId, array $increments, array $costs = []): void
    {
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        // Get or create the record
        $metric = UsageMetric::updateOrCreate(
            [
                'company_id' => $companyId,
                'period_start' => $periodStart,
                'metric_type' => 'monthly',
            ],
            [
                'period_end' => $periodEnd,
            ]
        );

        // Increment metrics atomically (each increment is a separate query for atomicity)
        foreach ($increments as $field => $value) {
            UsageMetric::where('company_id', $companyId)
                ->where('period_start', $periodStart)
                ->where('metric_type', 'monthly')
                ->increment($field, $value);
        }

        // Update costs (merge with existing costs)
        if (!empty($costs)) {
            $existingCosts = $metric->costs ?? [];
            foreach ($costs as $type => $amount) {
                $existingCosts[$type] = ($existingCosts[$type] ?? 0.0) + $amount;
            }
            $metric->costs = $existingCosts;
            $metric->save();
        }
    }

    /**
     * Get usage summary for a company.
     *
     * @param int $companyId Company ID
     * @param string $period Period type (daily, weekly, monthly)
     * @param int $limit Number of records to retrieve
     * @return array<UsageMetric> Usage metrics
     */
    public function getUsageSummary(int $companyId, string $period = 'monthly', int $limit = 12): array
    {
        return UsageMetric::where('company_id', $companyId)
            ->where('metric_type', $period)
            ->orderBy('period_start', 'desc')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Get current month's usage for a company.
     *
     * @param int $companyId Company ID
     * @return UsageMetric|null Current month usage metric
     */
    public function getCurrentMonthUsage(int $companyId): ?UsageMetric
    {
        $periodStart = now()->startOfMonth();

        return UsageMetric::where('company_id', $companyId)
            ->where('period_start', $periodStart)
            ->where('metric_type', 'monthly')
            ->first();
    }
}
