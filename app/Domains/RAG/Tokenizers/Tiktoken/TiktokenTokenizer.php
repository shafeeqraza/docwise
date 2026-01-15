<?php

namespace App\Domains\RAG\Tokenizers\Tiktoken;

use App\Domains\RAG\Tokenizers\TokenizerInterface;
use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

/**
 * Tiktoken tokenizer for OpenAI models.
 *
 * Follows Single Responsibility Principle (SRP): Only token counting logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements TokenizerInterface.
 */
class TiktokenTokenizer implements TokenizerInterface
{
    private Encoder $encoder;

    /**
     * Create a new TiktokenTokenizer instance.
     *
     * @param string $model The OpenAI model name (e.g., 'text-embedding-3-small', 'text-embedding-3-large')
     */
    public function __construct(string $model = 'text-embedding-3-small')
    {
        $provider = new EncoderProvider();
        $this->encoder = $provider->getForModel($model);
    }

    /**
     * Count tokens in text.
     *
     * @param string $text The text to count tokens for
     * @return int Number of tokens
     */
    public function countTokens(string $text): int
    {
        $tokens = $this->encoder->encode($text);
        return count($tokens);
    }

    /**
     * Get text length in tokens (for chunking purposes).
     *
     * @param string $text The text to measure
     * @return int Token length
     */
    public function getTokenLength(string $text): int
    {
        return $this->countTokens($text);
    }
}
