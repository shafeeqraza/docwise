<?php

namespace App\Domains\RAG\Services;

use App\Domains\RAG\DTOs\ChunkDTO;
use App\Models\UsageMetric;

/**
 * Service for tracking usage metrics.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for usage metric tracking.
 */
class UsageMetricService
{
    /**
     * Record document processing metrics.
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

        // Update daily metrics
        $this->updateDailyMetrics($companyId, [
            'documents_processed' => 1,
            'chunks_created' => $chunkCount,
            'embeddings_generated' => $embeddingsGenerated,
            'tokens_prompt' => $totalTokens, // Tokens used for document processing/embedding
        ]);

        // Update monthly metrics
        $this->updateMonthlyMetrics($companyId, [
            'documents_processed' => 1,
            'chunks_created' => $chunkCount,
            'embeddings_generated' => $embeddingsGenerated,
            'tokens_prompt' => $totalTokens,
        ]);
    }

    /**
     * Update daily usage metrics.
     *
     * @param int $companyId Company ID
     * @param array<string, int> $increments Metrics to increment
     * @return void
     */
    private function updateDailyMetrics(int $companyId, array $increments): void
    {
        $periodStart = now()->startOfDay();
        $periodEnd = now()->endOfDay();

        // Ensure record exists first
        UsageMetric::updateOrCreate(
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
    }

    /**
     * Update monthly usage metrics.
     *
     * @param int $companyId Company ID
     * @param array<string, int> $increments Metrics to increment
     * @return void
     */
    private function updateMonthlyMetrics(int $companyId, array $increments): void
    {
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        // Ensure record exists first
        UsageMetric::updateOrCreate(
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
    }
}
