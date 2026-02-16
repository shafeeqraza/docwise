<?php

namespace App\Services\V1\Chat\RAG;

use App\Domains\RAG\DTOs\ChunkDTO;
use App\Repositories\V1\Contracts\RetrievedChunkRepositoryInterface;

/**
 * Service for persisting retrieved chunks.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for
 * storing retrieved chunks associated with a message.
 */
class RetrievedChunksPersister
{
    public function __construct(
        private readonly RetrievedChunkRepositoryInterface $retrievedChunkRepository
    ) {}

    /**
     * Store retrieved chunks for a message.
     *
     * @param int $messageId Message ID
     * @param array<ChunkDTO> $chunks Retrieved chunks
     * @return void
     */
    public function persist(int $messageId, array $chunks): void
    {
        if (empty($chunks)) {
            return;
        }

        foreach ($chunks as $index => $chunkDTO) {
            $this->retrievedChunkRepository->create([
                'message_id' => $messageId,
                'chunk_id' => $chunkDTO->id,
                'document_id' => $chunkDTO->documentId,
                'similarity_score' => $chunkDTO->metadata['similarity_score'] ?? 0.0,
                'rank_position' => $index + 1,
                'used_in_context' => true,
                'metadata' => $chunkDTO->metadata,
                'created_at' => now(),
            ]);
        }
    }
}
