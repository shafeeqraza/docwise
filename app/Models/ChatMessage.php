<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'session_id',
        'role',
        'content',
        'content_type',
        'tokens_prompt',
        'tokens_completion',
        'model_used',
        'temperature',
        'latency_ms',
        'confidence_score',
        'raw_llm_response',
        'citations',
    ];

    protected function casts(): array
    {
        return [
            'raw_llm_response' => 'array',
            'citations' => 'array',
            'temperature' => 'decimal:2',
            'confidence_score' => 'decimal:3',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    // Relationships
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function retrievedChunks(): HasMany
    {
        return $this->hasMany(RetrievedChunk::class, 'message_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class, 'message_id');
    }

    // Helper methods
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isSystem(): bool
    {
        return $this->role === 'system';
    }

    public function getTotalTokens(): int
    {
        return ($this->tokens_prompt ?? 0) + ($this->tokens_completion ?? 0);
    }

    public function hasCitations(): bool
    {
        return !empty($this->citations);
    }

    public function getCitationCount(): int
    {
        return count($this->citations ?? []);
    }

    public function getFormattedContent(): string
    {
        if ($this->content_type === 'markdown') {
            // You might want to use a markdown parser here
            return $this->content;
        }
        
        if ($this->content_type === 'html') {
            return $this->content;
        }
        
        // Default to text, escape HTML
        return htmlspecialchars($this->content);
    }

    public function getResponseTime(): ?int
    {
        if (!$this->isAssistant()) {
            return null;
        }
        
        $previousMessage = ChatMessage::where('session_id', $this->session_id)
            ->where('created_at', '<', $this->created_at)
            ->where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$previousMessage) {
            return null;
        }
        
        return $previousMessage->created_at->diffInSeconds($this->created_at);
    }

    public function hasPositiveFeedback(): bool
    {
        return $this->feedback()->where('feedback_type', 'thumbs_up')->exists();
    }

    public function hasNegativeFeedback(): bool
    {
        return $this->feedback()->where('feedback_type', 'thumbs_down')->exists();
    }

    public function getAverageRating(): ?float
    {
        $ratings = $this->feedback()
            ->where('feedback_type', 'rating')
            ->whereNotNull('rating')
            ->pluck('rating');
        
        return $ratings->count() > 0 ? $ratings->average() : null;
    }
}