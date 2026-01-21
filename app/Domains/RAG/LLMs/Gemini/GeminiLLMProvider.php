<?php

namespace App\Domains\RAG\LLMs\Gemini;

use App\Domains\RAG\Contracts\LLMProvider;
use App\Domains\RAG\DTOs\CompletionDTO;
use App\Domains\RAG\Embeddings\Concerns\RetriesEmbeddingRequests;
use App\Services\V1\Common\LogService;
use Illuminate\Support\Facades\Http;

/**
 * Gemini LLM provider implementation.
 *
 * Follows Single Responsibility Principle (SRP): Only Gemini-specific LLM logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements LLMProvider interface.
 */
class GeminiLLMProvider implements LLMProvider
{
    use RetriesEmbeddingRequests;
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    protected int $timeout;

    public function __construct(?LogService $logService = null)
    {
        $this->logService = $logService;
        $this->apiKey = config('services.gemini.api_key');
        if (!$this->apiKey) {
            throw new \RuntimeException('Gemini API key not configured');
        }

        $this->timeout = config('services.gemini.timeout', 60);
    }

    /**
     * Convert human-readable model name to API format.
     *
     * @param string $model The model name (e.g., "Gemini 2.5 Flash")
     * @return string API model name (e.g., "gemini-2.5-flash")
     */
    protected function convertModelName(string $model): string
    {
        // Convert "Gemini 2.5 Flash" to "gemini-2.5-flash"
        $converted = strtolower($model);
        $converted = str_replace(' ', '-', $converted);
        return $converted;
    }

    /**
     * Normalize model name to ensure it has the "models/" prefix.
     *
     * @param string $model The model name
     * @return string Normalized model name
     */
    protected function normalizeModelName(string $model): string
    {
        if (!str_starts_with($model, 'models/')) {
            return "models/{$model}";
        }
        return $model;
    }

    /**
     * Get model name without "models/" prefix for endpoint URLs.
     *
     * @param string $model The model name
     * @return string Model name without prefix
     */
    protected function getModelNameForEndpoint(string $model): string
    {
        return str_replace('models/', '', $model);
    }

    /**
     * Generate a completion from messages.
     *
     * @param array<array<string, string>> $messages Array of message arrays with 'role' and 'content' keys
     * @param array<string, mixed> $options Additional options (temperature, max_tokens, model, etc.)
     * @return CompletionDTO The completion DTO
     * @throws \RuntimeException If completion generation fails
     */
    public function generateCompletion(array $messages, array $options = []): CompletionDTO
    {
        $model = $options['model'] ?? 'Gemini 2.5 Flash';
        // Normalize model name: "Gemini 2.5 Flash" -> "models/gemini-2.5-flash"
        $normalizedModel = $this->normalizeModelName($this->convertModelName($model));
        $modelName = $this->getModelNameForEndpoint($normalizedModel);

        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 2000;

        // Convert messages to Gemini format
        $contents = $this->convertMessagesToGeminiFormat($messages);

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        try {
            $response = $this->retryWithBackoff(
                function () use ($modelName, $payload) {
                    $response = Http::timeout($this->timeout)
                        ->withHeaders([
                            'x-goog-api-key' => $this->apiKey,
                            'Content-Type' => 'application/json',
                        ])
                        ->post("{$this->baseUrl}/models/{$modelName}:generateContent", $payload);

                    if (!$response->successful()) {
                        throw new \RuntimeException(
                            "Gemini API request failed: {$response->status()} - {$response->body()}"
                        );
                    }

                    return $response;
                },
                $normalizedModel,
                'Gemini',
                3
            );

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                throw new \RuntimeException('Invalid response from Gemini API: no candidates');
            }

            $content = $candidate['content']['parts'][0]['text'] ?? '';
            $usageMetadata = $data['usageMetadata'] ?? [];

            return new CompletionDTO(
                content: $content,
                model: $normalizedModel,
                tokensPrompt: $usageMetadata['promptTokenCount'] ?? 0,
                tokensCompletion: $usageMetadata['candidatesTokenCount'] ?? 0,
                finishReason: $candidate['finishReason'] ?? 'STOP',
                rawResponse: $data
            );
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to generate Gemini completion: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Convert messages array to Gemini format.
     *
     * @param array<array<string, string>> $messages Array of message arrays with 'role' and 'content' keys
     * @return array Array of contents in Gemini format
     */
    protected function convertMessagesToGeminiFormat(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';

            // Map roles: system -> user, user -> user, assistant -> model
            $geminiRole = match ($role) {
                'system' => 'user',
                'user' => 'user',
                'assistant' => 'model',
                default => 'user',
            };

            $contents[] = [
                'role' => $geminiRole,
                'parts' => [
                    ['text' => $content]
                ],
            ];
        }

        return $contents;
    }

    /**
     * Check if this provider supports the given model.
     *
     * @param string $model The model name
     * @return bool True if the provider supports the model
     */
    public function supports(string $model): bool
    {
        $normalized = strtolower($model);

        // Check for Gemini models (handles "Gemini 2.5 Flash", "gemini-2.5-flash", "models/gemini-2.5-flash", etc.)
        return str_contains($normalized, 'gemini') ||
            str_contains($normalized, 'models/gemini') ||
            str_contains($normalized, 'gemini-2.5') ||
            str_contains($normalized, 'gemini 2.5') ||
            str_contains($normalized, 'gemini-1.5') ||
            str_contains($normalized, 'gemini 1.5') ||
            str_contains($normalized, 'gemini-pro');
    }
}
