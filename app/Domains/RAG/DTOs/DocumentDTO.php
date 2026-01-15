<?php

namespace App\Domains\RAG\DTOs;

/**
 * Data Transfer Object for document data.
 *
 * Follows Single Responsibility Principle (SRP): Only data transfer, no behavior.
 * Immutable: Properties are readonly.
 */
readonly class DocumentDTO
{
    /**
     * @param int|null $id Document ID
     * @param string $title Document title
     * @param string $content Document text content
     * @param string $fileType File type (pdf, docx, txt, etc.)
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        public ?int $id,
        public string $title,
        public string $content,
        public string $fileType,
        public array $metadata = []
    ) {
        $this->validate();
    }

    /**
     * Validate the DTO data.
     *
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    private function validate(): void
    {
        if (empty(trim($this->title))) {
            throw new \InvalidArgumentException('Document title cannot be empty');
        }

        if (empty(trim($this->fileType))) {
            throw new \InvalidArgumentException('File type cannot be empty');
        }

        $allowedTypes = ['pdf', 'docx', 'txt', 'html', 'md'];
        if (!in_array(strtolower($this->fileType), $allowedTypes)) {
            throw new \InvalidArgumentException("Unsupported file type: {$this->fileType}");
        }
    }
}
