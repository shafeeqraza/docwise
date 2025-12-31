<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
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
            'company_id' => $this->company_id,
            'title' => $this->title,
            'description' => $this->description,
            'source_type' => $this->source_type,
            'file_type' => $this->file_type,
            'status' => $this->status,
            'uploaded_by' => $this->uploaded_by,
            'public_id' => $this->public_id,
            'file_url' => $this->file_url,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->getFileSizeFormatted(),
            'checksum' => $this->checksum,
            'language' => $this->language,
            'tags' => $this->tags ?? [],
            'metadata' => $this->metadata ?? [],
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            
            // Relationships (only included if loaded)
            'uploaded_by_user' => $this->whenLoaded('uploadedBy', function () {
                return [
                    'id' => $this->uploadedBy->id,
                    'name' => $this->uploadedBy->name,
                    'email' => $this->uploadedBy->email,
                ];
            }),
        ];
    }
}

