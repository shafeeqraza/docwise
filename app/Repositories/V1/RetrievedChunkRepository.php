<?php

namespace App\Repositories\V1;

use App\Models\RetrievedChunk;
use App\Repositories\V1\Contracts\RetrievedChunkRepositoryInterface;

class RetrievedChunkRepository implements RetrievedChunkRepositoryInterface
{
    /**
     * Create a retrieved chunk record.
     *
     * @param array $data
     * @return RetrievedChunk
     */
    #[\Override]
    public function create(array $data): RetrievedChunk
    {
        return RetrievedChunk::create($data);
    }
}
