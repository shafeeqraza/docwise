<?php

namespace App\Domains\RAG\Loaders\Docx;

use App\Domains\RAG\Contracts\DocumentLoader;
use App\Domains\RAG\Exceptions\TextExtractionException;
use App\Domains\RAG\Exceptions\UnsupportedDocumentTypeException;
use App\Services\V1\Document\Upload\FileStorageService;

/**
 * DOCX document loader.
 *
 * Follows Single Responsibility Principle (SRP): Only DOCX-specific loading logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements DocumentLoader interface.
 * Follows Dependency Inversion Principle (DIP): Depends on FileStorageService abstraction.
 */
class DocxDocumentLoader implements DocumentLoader
{
    public function __construct(
        private FileStorageService $fileStorageService
    ) {}

    /**
     * Load and extract text content from a DOCX document file.
     *
     * @param string $filePath The URL or path to the DOCX file
     * @return string The extracted text content
     * @throws UnsupportedDocumentTypeException If the file type is not DOCX
     * @throws TextExtractionException If file cannot be loaded or extracted
     */
    #[\Override]
    public function load(string $filePath): string
    {
        $fileType = $this->extractFileType($filePath);

        if (strtolower($fileType) !== 'docx') {
            throw new UnsupportedDocumentTypeException(
                "DocxDocumentLoader only supports DOCX files, got: {$fileType}"
            );
        }

        $tempFile = $this->createTempFile();

        try {
            // Download file to temporary location
            $publicId = $this->extractPublicId($filePath);
            $fileContent = $this->fileStorageService->downloadFile($filePath, $publicId);
            $bytesWritten = file_put_contents($tempFile, $fileContent);

            if ($bytesWritten === false) {
                throw new TextExtractionException("Failed to write downloaded DOCX to temporary location");
            }

            // Extract text from DOCX
            return $this->extractDocxText($tempFile);
        } catch (\Exception $e) {
            if ($e instanceof UnsupportedDocumentTypeException || $e instanceof TextExtractionException) {
                throw $e;
            }
            throw new TextExtractionException(
                "Failed to load DOCX file from: {$filePath}. {$e->getMessage()}",
                0,
                $e
            );
        } finally {
            $this->cleanupTempFile($tempFile);
        }
    }

    /**
     * Extract text from DOCX file using PHPWord.
     *
     * @param string $filePath Path to the DOCX file
     * @return string Extracted text
     * @throws TextExtractionException If extraction fails
     */
    private function extractDocxText(string $filePath): string
    {
        // Check if PHPWord is available
        if (!class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            throw new TextExtractionException(
                'PHPWord library is not installed. Install with: composer require phpoffice/phpword'
            );
        }

        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    }
                }
            }

            $text = trim($text);

            if (empty($text)) {
                throw new TextExtractionException('No text could be extracted from the DOCX file');
            }

            return $text;
        } catch (\Exception $e) {
            if ($e instanceof TextExtractionException) {
                throw $e;
            }
            throw new TextExtractionException(
                "Failed to extract text from DOCX file: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Create a temporary file for processing.
     *
     * @return string Path to temporary file
     * @throws TextExtractionException If temporary file cannot be created
     */
    private function createTempFile(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'docwise_docx_');

        if ($tempFile === false) {
            throw new TextExtractionException('Failed to create temporary file for DOCX processing');
        }

        return $tempFile;
    }

    /**
     * Clean up temporary file.
     *
     * @param string $tempFile Path to temporary file
     * @return void
     */
    private function cleanupTempFile(string $tempFile): void
    {
        if (file_exists($tempFile)) {
            @unlink($tempFile);
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
