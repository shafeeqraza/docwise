<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum IngestionJobType: string
{
    use HasValues;

    case PARSE_DOCUMENT = 'parse_document';
    case GENERATE_EMBEDDINGS = 'generate_embeddings';
    case SYNC_QDRANT = 'sync_qdrant';
    case CLEANUP = 'cleanup';
}
