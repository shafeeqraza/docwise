<?php

namespace App\Domains\RAG\LLMs\OpenAI;

use App\Domains\RAG\Contracts\LLMProvider;
use App\Domains\RAG\DTOs\CompletionDTO;
use App\Domains\RAG\Embeddings\Concerns\RetriesEmbeddingRequests;
use App\Services\V1\Common\LogService;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI LLM provider implementation.
 *
 * Follows Single Responsibility Principle (SRP): Only OpenAI-specific LLM logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements LLMProvider interface.
 */
class OpenAILLMProvider implements LLMProvider
{
    use RetriesEmbeddingRequests;
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1';
    protected int $timeout;
    protected string $organization;
    protected string $project;

    public function __construct(?LogService $logService = null)
    {
        $this->logService = $logService;
        $this->apiKey = config('services.openai.api_key');
        if (!$this->apiKey) {
            throw new \RuntimeException('OpenAI API key not configured');
        }

        $this->timeout = config('services.openai.timeout', 60);
        $this->organization = config('services.openai.organization', '');
        $this->project = config('services.openai.project', '');
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
        $model = $options['model'] ?? 'gpt-4';
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 2000;

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($this->organization) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        if ($this->project) {
            $headers['OpenAI-Project'] = $this->project;
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        try {
            $response = $this->retryWithBackoff(
                function () use ($headers, $payload) {
                    $response = Http::timeout($this->timeout)
                        ->withHeaders($headers)
                        ->post("{$this->baseUrl}/chat/completions", $payload);

                    if (!$response->successful()) {
                        throw new \RuntimeException(
                            "OpenAI API request failed: {$response->status()} - {$response->body()}"
                        );
                    }

                    return $response;
                },
                $model,
                'OpenAI',
                3
            );

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if (!$choice) {
                throw new \RuntimeException('Invalid response from OpenAI API: no choices');
            }

            $message = $choice['message'] ?? [];
            $usage = $data['usage'] ?? [];

            return new CompletionDTO(
                content: $message['content'] ?? '',
                model: $data['model'] ?? $model,
                tokensPrompt: $usage['prompt_tokens'] ?? 0,
                tokensCompletion: $usage['completion_tokens'] ?? 0,
                finishReason: $choice['finish_reason'] ?? 'stop',
                rawResponse: $data
            );
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to generate OpenAI completion: {$e->getMessage()}",
                0,
                $e
            );
        }
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

        // Check for OpenAI models
        return str_contains($normalized, 'gpt') ||
            str_contains($normalized, 'openai') ||
            str_starts_with($normalized, 'o1') ||
            str_starts_with($normalized, 'o3');
    }
}
