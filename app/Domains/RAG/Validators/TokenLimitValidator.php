<?php

namespace App\Domains\RAG\Validators;

use App\Domains\RAG\DTOs\ChunkDTO;
use App\Exceptions\TokenLimitExceededException;
use App\Models\Company;

/**
 * Validator for token limit checks.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for token limit validation.
 * Follows Dependency Inversion Principle (DIP): Works with DTOs and models, not concrete implementations.
 */
class TokenLimitValidator
{
    /**
     * Validate token limit for chunks before processing step.
     *
     * @param array<ChunkDTO> $chunks Array of chunks to validate
     * @param Company $company Company model
     * @return void
     * @throws TokenLimitExceededException If token limit is exceeded
     */
    public function validate(int $companyId, array $chunks): void
    {
        $company = Company::findOrFail($companyId);

        if (empty($chunks)) {
            return;
        }

        // Calculate total tokens from chunks
        $requestedTokens = $this->calculateTotalTokens($chunks);

        // Get token limit and current usage
        $tokenLimit = $company->getTokenLimit();
        $currentUsage = $company->getCurrentTokenUsage();
        $totalAfterOperation = $currentUsage + $requestedTokens;

        // Check if limit would be exceeded
        if ($totalAfterOperation > $tokenLimit && !$company->allow_overages) {
            throw TokenLimitExceededException::withTokenDetails(
                $requestedTokens,
                $currentUsage,
                $tokenLimit,
                $company->allow_overages
            );
        }
    }

    /**
     * Calculate total tokens from chunks.
     *
     * @param array<ChunkDTO> $chunks Array of chunks
     * @return int Total token count
     */
    private function calculateTotalTokens(array $chunks): int
    {
        return array_sum(array_map(fn($chunk) => $chunk->tokens, $chunks));
    }
}
