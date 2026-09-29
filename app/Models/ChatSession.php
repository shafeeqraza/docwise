<?php

namespace App\Models;

use App\Enums\ChatMessageRole;
use App\Enums\ChatSessionChannel;
use App\Enums\ChatSessionStatus;
use App\Policies\ChatSessionPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[UsePolicy(ChatSessionPolicy::class)]
class ChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'external_user_id',
        'channel',
        'title',
        'status',
        'language',
        'user_metadata',
        'context',
        'message_count',
        'total_tokens',
        'satisfaction_rating',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'user_metadata' => 'array',
            'context' => 'array',
            'ended_at' => 'datetime',
            'channel' => ChatSessionChannel::class,
            'status' => ChatSessionStatus::class,
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
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class, 'session_id');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === ChatSessionStatus::ACTIVE;
    }

    public function isResolved(): bool
    {
        return $this->status === ChatSessionStatus::RESOLVED;
    }

    public function isEscalated(): bool
    {
        return $this->status === ChatSessionStatus::ESCALATED;
    }

    public function getLastMessage(): ?ChatMessage
    {
        return $this->messages()->latest()->first();
    }

    public function getLastUserMessage(): ?ChatMessage
    {
        return $this->messages()->where('role', ChatMessageRole::USER)->latest()->first();
    }

    public function getLastAssistantMessage(): ?ChatMessage
    {
        return $this->messages()->where('role', ChatMessageRole::ASSISTANT)->latest()->first();
    }

    public function getDuration(): ?int
    {
        if (!$this->ended_at) {
            return $this->created_at->diffInSeconds(now());
        }

        return $this->created_at->diffInSeconds($this->ended_at);
    }

    public function getAverageResponseTime(): ?float
    {
        $messages = $this->messages()->orderBy('created_at')->get();
        $responseTimes = [];

        for ($i = 0; $i < count($messages) - 1; $i++) {
            $current = $messages[$i];
            $next = $messages[$i + 1];

            if ($current->role === ChatMessageRole::USER && $next->role === ChatMessageRole::ASSISTANT) {
                $responseTimes[] = $current->created_at->diffInSeconds($next->created_at);
            }
        }

        return count($responseTimes) > 0 ? array_sum($responseTimes) / count($responseTimes) : null;
    }

    public function getUserName(): ?string
    {
        return $this->user_metadata['name'] ?? $this->user_metadata['email'] ?? $this->external_user_id;
    }

    public function getUserEmail(): ?string
    {
        return $this->user_metadata['email'] ?? null;
    }

    public function addContext(string $key, $value): void
    {
        $context = $this->context ?? [];
        $context[$key] = $value;
        $this->update(['context' => $context]);
    }

    public function getContext(string $key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }
}
