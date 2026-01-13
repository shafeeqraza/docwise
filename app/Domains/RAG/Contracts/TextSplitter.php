<?php

namespace App\Domains\RAG\Contracts;

/**
 * Contract for splitting text into chunks.
 *
 * Follows Interface Segregation Principle (ISP): Single focused responsibility.
 */
interface TextSplitter
{
    /**
     * Split text into smaller chunks.
     *
     * @param string $text The text to split
     * @return array<string> Array of text chunks
     */
    public function splitText(string $text): array;
}
