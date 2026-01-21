<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->uuid,
            'message' => $this->when($this->relationLoaded('latestMessage'), function () {
                return new ChatMessageResource($this->latestMessage);
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
