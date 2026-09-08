<?php

namespace App\Domains\RAG\DTOs;

use App\Enums\DocumentFileType;

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
     * @param DocumentFileType $fileType File type
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        public ?int $id,
        public string $title,
        public string $content,
        public DocumentFileType $fileType,
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

    }
}
