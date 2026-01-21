<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class DocumentValidationException extends Exception
{
    public function __construct(string $message = "Document validation failed", int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
