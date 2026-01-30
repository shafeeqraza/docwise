<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Application-level exception for vector store operation failures.
 * Used for any vector store driver (Qdrant, PostgreSQL, etc.).
 */
class VectorStoreException extends RuntimeException
{
    //
}
