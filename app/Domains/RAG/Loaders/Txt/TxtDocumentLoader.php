<?php

namespace App\Domains\RAG\Loaders\Txt;

use App\Domains\RAG\Contracts\DocumentLoader;
use App\Domains\RAG\Exceptions\TextExtractionException;
use App\Domains\RAG\Exceptions\UnsupportedDocumentTypeException;
use App\Services\V1\Document\Upload\FileStorageService;

/**
 * Text document loader for txt, html, and md files.
 *
 * Follows Single Responsibility Principle (SRP): Only text file loading logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements DocumentLoader interface.
 * Follows Dependency Inversion Principle (DIP): Depends on FileStorageService abstraction.
 */
class TxtDocumentLoader implements DocumentLoader
{
    private const SUPPORTED_TYPES = ['txt', 'html', 'md'];

    public function __construct(
        private FileStorageService $fileStorageService
    ) {}

    /**
     * Load and extract text content from a text-based document file.
     *
     * @param string $filePath The URL or path to the document file
     * @return string The extracted text content
     * @throws UnsupportedDocumentTypeException If the file type is not supported
     * @throws TextExtractionException If file cannot be loaded or read
     */
    #[\Override]
    public function load(string $filePath): string
    {
        // Extract file extension from path/URL
        $fileType = $this->extractFileType($filePath);

        if (!in_array(strtolower($fileType), self::SUPPORTED_TYPES)) {
            throw new UnsupportedDocumentTypeException(
                "TxtDocumentLoader does not support file type: {$fileType}. Supported types: " . implode(', ', self::SUPPORTED_TYPES)
            );
        }

        try {
            // For URLs, extract public_id if possible, otherwise use the URL
            $publicId = $this->extractPublicId($filePath);
            return $this->fileStorageService->downloadFile($filePath, $publicId);
        } catch (\Exception $e) {
            throw new TextExtractionException(
                "Failed to load text file from: {$filePath}. {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Extract file type from file path/URL.
     *
     * @param string $filePath The file path or URL
     * @return string The file extension
     */
    private function extractFileType(string $filePath): string
    {
        $pathInfo = pathinfo(parse_url($filePath, PHP_URL_PATH) ?: $filePath);
        return strtolower($pathInfo['extension'] ?? '');
    }

    /**
     * Extract public ID from Cloudinary URL if possible.
     *
     * @param string $filePath The file path or URL
     * @return string The public ID or the original path
     */
    private function extractPublicId(string $filePath): string
    {
        // Try to extract public_id from Cloudinary URL
        if (preg_match('/\/v\d+\/(.+)$/', $filePath, $matches)) {
            return pathinfo($matches[1], PATHINFO_FILENAME);
        }

        return $filePath;
    }
}
