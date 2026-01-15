<?php

namespace App\Domains\RAG\Contracts;

use App\Domains\RAG\Exceptions\UnsupportedDocumentTypeException;

/**
 * Contract for loading/extracting text from documents.
 *
 * Follows Interface Segregation Principle (ISP): Single focused responsibility.
 */
interface DocumentLoader
{
    /**
     * Load and extract text content from a document file.
     *
     * @param string $filePath The path or URL to the document file
     * @return string The extracted text content
     * @throws UnsupportedDocumentTypeException If the file type is not supported
     * @throws \RuntimeException If file cannot be loaded or read
     */
    public function load(string $filePath): string;
}
