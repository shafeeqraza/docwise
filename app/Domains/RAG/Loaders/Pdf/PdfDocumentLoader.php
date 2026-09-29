<?php

namespace App\Domains\RAG\Loaders\Pdf;

use App\Domains\RAG\Contracts\DocumentLoader;
use App\Domains\RAG\Exceptions\TextExtractionException;
use App\Domains\RAG\Exceptions\UnsupportedDocumentTypeException;
use App\Services\V1\Document\Upload\FileStorageService;
use Spatie\PdfToText\Pdf;

/**
 * PDF document loader.
 *
 * Follows Single Responsibility Principle (SRP): Only PDF-specific loading logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements DocumentLoader interface.
 * Follows Dependency Inversion Principle (DIP): Depends on FileStorageService abstraction.
 */
class PdfDocumentLoader implements DocumentLoader
{
    public function __construct(
        private FileStorageService $fileStorageService
    ) {}

    /**
     * Load and extract text content from a PDF document file.
     *
     * @param string $filePath The URL or path to the PDF file
     * @return string The extracted text content
     * @throws UnsupportedDocumentTypeException If the file type is not PDF
     * @throws TextExtractionException If file cannot be loaded or extracted
     */
    #[\Override]
    public function load(string $filePath): string
    {
        $fileType = $this->extractFileType($filePath);

        if (strtolower($fileType) !== 'pdf') {
            throw new UnsupportedDocumentTypeException(
                "PdfDocumentLoader only supports PDF files, got: {$fileType}"
            );
        }

        $tempFile = $this->createTempFile();

        try {
            // Download file to temporary location
            $publicId = $this->extractPublicId($filePath);
            $fileContent = $this->fileStorageService->downloadFile($filePath, $publicId);
            $bytesWritten = file_put_contents($tempFile, $fileContent);

            if ($bytesWritten === false) {
                throw new TextExtractionException("Failed to write downloaded PDF to temporary location");
            }

            // Extract text from PDF
            return $this->extractPdfText($tempFile);
        } catch (\Exception $e) {
            if ($e instanceof UnsupportedDocumentTypeException || $e instanceof TextExtractionException) {
                throw $e;
            }
            throw new TextExtractionException(
                "Failed to load PDF file from: {$filePath}. {$e->getMessage()}",
                0,
                $e
            );
        } finally {
            $this->cleanupTempFile($tempFile);
        }
    }

    /**
     * Extract text from PDF file using pdftotext.
     *
     * @param string $filePath Path to the PDF file
     * @return string Extracted text
     * @throws TextExtractionException If extraction fails
     */
    private function extractPdfText(string $filePath): string
    {
        $pdfToTextPath = config('services.pdf_to_text_path');

        $text = Pdf::getText($filePath, $pdfToTextPath);

        if (empty(trim($text))) {
            throw new TextExtractionException('No text could be extracted from the PDF file');
        }

        return $text;
    }

    /**
     * Create a temporary file for processing.
     *
     * @return string Path to temporary file
     * @throws TextExtractionException If temporary file cannot be created
     */
    private function createTempFile(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'docwise_pdf_');

        if ($tempFile === false) {
            throw new TextExtractionException('Failed to create temporary file for PDF processing');
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
