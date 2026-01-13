<?php

namespace App\Domains\RAG\Services;

use App\Domains\RAG\Exceptions\TextExtractionException as RAGTextExtractionException;
use App\Domains\RAG\Factories\DocumentLoaderFactory;
use App\Exceptions\TextExtractionException;
use App\Models\Document;

/**
 * Service for extracting text from documents.
 *
 * Follows Adapter Pattern: Adapts domain loaders to application layer needs.
 * Follows Single Responsibility Principle (SRP): Only text extraction logic.
 */
class DocumentTextExtractionService
{
    public function __construct(
        private DocumentLoaderFactory $loaderFactory
    ) {}

    /**
     * Extract text from a document based on its file type.
     *
     * @param Document $document The document model
     * @return string The extracted text
     * @throws TextExtractionException If extraction fails
     */
    public function extractText(Document $document): string
    {
        try {
            $loader = $this->loaderFactory->create($document->file_type);
            return $loader->load($document->file_url);
        } catch (RAGTextExtractionException $e) {
            throw new TextExtractionException($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            throw new TextExtractionException(
                "Failed to extract text from document: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
