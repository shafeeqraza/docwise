<?php

namespace App\Domains\RAG\Factories;

use App\Domains\RAG\Contracts\DocumentLoader;
use App\Enums\DocumentFileType;
use App\Domains\RAG\Exceptions\UnsupportedDocumentTypeException;
use App\Domains\RAG\Loaders\Docx\DocxDocumentLoader;
use App\Domains\RAG\Loaders\Pdf\PdfDocumentLoader;
use App\Domains\RAG\Loaders\Txt\TxtDocumentLoader;
use Illuminate\Contracts\Container\Container;

/**
 * Factory for creating document loaders based on file type.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for loader creation.
 * Follows Open/Closed Principle (OCP): Easy to add new loaders without modifying factory.
 * Follows Dependency Inversion Principle (DIP): Returns interface type, uses container for resolution.
 */
class DocumentLoaderFactory
{
    /**
     * Mapping of file types to loader classes, keyed by DocumentFileType value.
     *
     * @var array<string, class-string<DocumentLoader>>
     */
    private array $loaderMap = [
        DocumentFileType::PDF->value => PdfDocumentLoader::class,
        DocumentFileType::DOCX->value => DocxDocumentLoader::class,
        DocumentFileType::TXT->value => TxtDocumentLoader::class,
        DocumentFileType::HTML->value => TxtDocumentLoader::class,
        DocumentFileType::MD->value => TxtDocumentLoader::class,
    ];

    public function __construct(
        private Container $container
    ) {}

    /**
     * Create a document loader for the given file type.
     *
     * @param DocumentFileType $fileType The file type
     * @return DocumentLoader The appropriate loader instance
     * @throws UnsupportedDocumentTypeException If no loader is registered for the type
     */
    public function create(DocumentFileType $fileType): DocumentLoader
    {
        if (!isset($this->loaderMap[$fileType->value])) {
            throw new UnsupportedDocumentTypeException(
                "Unsupported document type: {$fileType->value}. Supported types: " . implode(', ', array_keys($this->loaderMap))
            );
        }

        $loaderClass = $this->loaderMap[$fileType->value];

        // Resolve loader from container (allows dependency injection)
        return $this->container->make($loaderClass);
    }

    /**
     * Register a custom loader for a file type.
     *
     * @param DocumentFileType $fileType The file type
     * @param class-string<DocumentLoader> $loaderClass The loader class
     * @return void
     */
    public function register(DocumentFileType $fileType, string $loaderClass): void
    {
        if (!is_subclass_of($loaderClass, DocumentLoader::class)) {
            throw new \InvalidArgumentException(
                "Loader class {$loaderClass} must implement " . DocumentLoader::class
            );
        }

        $this->loaderMap[$fileType->value] = $loaderClass;
    }

    /**
     * Get all supported file types.
     *
     * @return array<string> Array of supported file types
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->loaderMap);
    }
}
