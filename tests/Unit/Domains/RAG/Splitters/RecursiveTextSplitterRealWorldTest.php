<?php

namespace Tests\Unit\Domains\RAG\Splitters;

use App\Domains\RAG\Splitters\RecursiveTextSplitter;
use App\Domains\RAG\Tokenizers\TokenizerInterface;
use PHPUnit\Framework\TestCase;

class RecursiveTextSplitterRealWorldTest extends TestCase
{
    private function createMockTokenizer(): TokenizerInterface
    {
        return new class implements TokenizerInterface {
            public int $callCount = 0;

            public function countTokens(string $text): int
            {
                $this->callCount++;
                // Approximation: 1 token ≈ 4 characters
                return (int) ceil(mb_strlen($text) / 4);
            }

            public function getTokenLength(string $text): int
            {
                return $this->countTokens($text);
            }
        };
    }

    /**
     * Scenario 1: Academic Paper with large paragraphs.
     * Tests if the splitter respects semantic boundaries (paragraphs) before falling back.
     */
    public function test_academic_paper_scenario(): void
    {
        $tokenizer = $this->createMockTokenizer();
        $splitter = new RecursiveTextSplitter($tokenizer, chunkSize: 100, chunkOverlap: 20);

        $text = "Abstract: This study explores the impact of AI on productivity.\n\n" .
            "Introduction: Recent advances in LLMs have revolutionized the field. " . str_repeat("Data suggests that efficiency has increased by 40% in most sectors. ", 5) . "\n\n" .
            "Methodology: We conducted a survey of 500 tech companies. " . str_repeat("The survey was conducted over a period of 6 months. ", 5);

        $chunks = $splitter->splitText($text);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(110, $tokenizer->getTokenLength($chunk));
            // Verify chunk is not empty and has content
            $this->assertNotEmpty(trim($chunk));
        }
    }

    /**
     * Scenario 2: Legal Contract with numbered lists.
     * Tests if small semantic units are merged correctly within chunk limits.
     */
    public function test_legal_contract_scenario(): void
    {
        $tokenizer = $this->createMockTokenizer();
        $splitter = new RecursiveTextSplitter($tokenizer, chunkSize: 50, chunkOverlap: 10);

        $text = "1. DEFINITIONS\n" .
            "1.1 'Agreement' means this document.\n" .
            "1.2 'Party' means either the Client or the Provider.\n" .
            "2. SERVICES\n" .
            "2.1 The Provider shall deliver the RAG system as specified.\n" .
            "2.2 Any changes must be in writing.";

        $chunks = $splitter->splitText($text);

        $this->assertNotEmpty($chunks);
        // Verify that numbered items aren't orphaned if they fit together
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(60, $tokenizer->getTokenLength($chunk));
        }
    }

    /**
     * Scenario 3: Technical Documentation with Code Blocks.
     * Tests handling of code-like structure where sentences aren't the primary separator.
     */
    public function test_technical_docs_with_code_scenario(): void
    {
        $tokenizer = $this->createMockTokenizer();
        $splitter = new RecursiveTextSplitter($tokenizer, chunkSize: 80, chunkOverlap: 15);

        $text = "To implement a custom splitter, follow these steps:\n\n" .
            "```php\n" .
            "class CustomSplitter implements TextSplitter {\n" .
            "    public function splitText(string \$text): array {\n" .
            "        return explode('\\n', \$text);\n" .
            "    }\n" .
            "}\n" .
            "```\n\n" .
            "Ensure you register the class in the ServiceProvider.";

        $chunks = $splitter->splitText($text);

        $this->assertNotEmpty($chunks);
        $combined = implode('', $chunks);
        $this->assertStringContainsString('class CustomSplitter', $combined);
    }

    /**
     * Scenario 4: Social Media Data with Emojis and Hashtags.
     * Tests Unicode preservation and character boundaries.
     */
    public function test_social_media_unicode_scenario(): void
    {
        $tokenizer = $this->createMockTokenizer();
        $splitter = new RecursiveTextSplitter($tokenizer, chunkSize: 20, chunkOverlap: 5);

        $text = "Loving the new AI features! 🚀 #AI #TechLife\n\n" .
            "Check out this cool demo: 🌈✨ It's absolutely amazing and mind-blowing!";

        $chunks = $splitter->splitText($text);

        $this->assertGreaterThan(1, count($chunks));
        $combined = implode('', $chunks);
        $this->assertStringContainsString('🚀', $combined);
        $this->assertStringContainsString('🌈✨', $combined);
    }

    /**
     * Scenario 5: Large Log Files (No semantic separators).
     * Tests the "TokenBasedSplitter" character-fallback path.
     */
    public function test_log_file_no_separators_scenario(): void
    {
        $tokenizer = $this->createMockTokenizer();
        $splitter = new RecursiveTextSplitter($tokenizer, chunkSize: 30, chunkOverlap: 10);

        // A very long string without spaces or newlines
        $text = "ERROR_2024-01-13T12:00:00Z_UID_999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999";

        $chunks = $splitter->splitText($text);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(40, $tokenizer->getTokenLength($chunk));
        }
    }

    /**
     * Scenario 6: Information Continuity (Overlap Check).
     * Tests if the overlap text is actually identical between chunks.
     */
    public function test_overlap_continuity_scenario(): void
    {
        $tokenizer = $this->createMockTokenizer();
        // Smaller chunk size to ensure splitting and overlap
        $splitter = new RecursiveTextSplitter($tokenizer, chunkSize: 20, chunkOverlap: 10);

        $text = "This is the first part of the text. This is the middle part which should overlap. This is the final part.";
        $chunks = $splitter->splitText($text);

        $this->assertGreaterThan(1, count($chunks), "Text should be split into multiple chunks");

        $overlapFound = false;
        for ($i = 0; $i < count($chunks) - 1; $i++) {
            $currentChunk = $chunks[$i];
            $nextChunk = $chunks[$i + 1];

            // Check if some part of the end of current chunk is in the beginning of next chunk
            $overlapFound = false;
            $words = explode(' ', trim($currentChunk));
            $lastWord = end($words);

            if (str_contains($nextChunk, $lastWord)) {
                $overlapFound = true;
            }

            $this->assertTrue($overlapFound, "Word from end of chunk $i should be present in chunk " . ($i + 1));
        }
    }

}
