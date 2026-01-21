<?php

namespace App\Repositories\V1\Contracts;

use App\Models\RetrievedChunk;

interface RetrievedChunkRepositoryInterface
{
    /**
     * Create a retrieved chunk record.
     *
     * @param array $data
     * @return RetrievedChunk
     */
    public function create(array $data): RetrievedChunk;
}
