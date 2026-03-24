<?php

namespace App\Domains\RAG\Exceptions;

use RuntimeException;

/**
 * Exception thrown when vector store operations fail.
 *
 * Generic exception for all vector store drivers (Qdrant, PostgreSQL, etc.).
 */
class VectorStoreException extends RuntimeException
{
    //
}
