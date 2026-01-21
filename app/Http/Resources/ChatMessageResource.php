<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'role' => $this->role,
            'content' => $this->content,
            'citations' => $this->citations,
            'confidence_score' => $this->confidence_score,
            'tokens_prompt' => $this->tokens_prompt,
            'tokens_completion' => $this->tokens_completion,
            'model_used' => $this->model_used,
            'latency_ms' => $this->latency_ms,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
