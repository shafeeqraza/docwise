<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResponseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Token is set in an HttpOnly cookie; do not expose it to JS in response body.
            'token_type' => 'Cookie',
            'user' => new SuperAdminResource($this->resource['user'] ?? null),
        ];
    }
}
