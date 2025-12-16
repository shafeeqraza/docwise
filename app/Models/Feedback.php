<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'message_id',
        'session_id',
        'user_id',
        'feedback_type',
        'rating',
        'comment',
        'categories',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'metadata' => 'array',
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
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function isPositive(): bool
    {
        return $this->feedback_type === 'thumbs_up' || 
               ($this->feedback_type === 'rating' && $this->rating >= 4);
    }

    public function isNegative(): bool
    {
        return $this->feedback_type === 'thumbs_down' || 
               ($this->feedback_type === 'rating' && $this->rating <= 2);
    }

    public function isNeutral(): bool
    {
        return $this->feedback_type === 'rating' && $this->rating === 3;
    }

    public function getSentiment(): string
    {
        if ($this->isPositive()) {
            return 'positive';
        }
        
        if ($this->isNegative()) {
            return 'negative';
        }
        
        return 'neutral';
    }

    public function hasComment(): bool
    {
        return !empty($this->comment);
    }

    public function hasCategories(): bool
    {
        return !empty($this->categories);
    }

    public function getCategoriesString(): string
    {
        if (!$this->hasCategories()) {
            return '';
        }
        
        return implode(', ', $this->categories);
    }

    public function getFormattedRating(): string
    {
        if ($this->feedback_type !== 'rating' || !$this->rating) {
            return '';
        }
        
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }
}