<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        'target_company_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    // Relationships
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function targetCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'target_company_id');
    }

    // Helper methods
    public static function log(string $action, ?int $targetCompanyId = null, array $details = []): void
    {
        $user = auth()->user();
        
        if (!$user || !$user->isSuperAdmin()) {
            return;
        }
        
        static::create([
            'admin_user_id' => $user->id,
            'target_company_id' => $targetCompanyId,
            'action' => $action,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function getActionDescription(): string
    {
        $descriptions = [
            'company.created' => 'Created company',
            'company.updated' => 'Updated company',
            'company.deleted' => 'Deleted company',
            'company.suspended' => 'Suspended company',
            'company.activated' => 'Activated company',
            'user.created' => 'Created user',
            'user.updated' => 'Updated user',
            'user.deleted' => 'Deleted user',
            'user.impersonated' => 'Impersonated user',
            'document.deleted' => 'Deleted document',
            'document.reprocessed' => 'Reprocessed document',
            'billing.invoice_created' => 'Created invoice',
            'billing.payment_processed' => 'Processed payment',
            'system.backup_created' => 'Created system backup',
            'system.maintenance_mode' => 'Enabled maintenance mode',
        ];
        
        return $descriptions[$this->action] ?? $this->action;
    }

    public function getTargetDescription(): string
    {
        if (!$this->targetCompany) {
            return 'System-wide';
        }
        
        return $this->targetCompany->name;
    }

    public function getDetailsString(): string
    {
        if (!$this->details) {
            return '';
        }
        
        $parts = [];
        foreach ($this->details as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $parts[] = "{$key}: {$value}";
        }
        
        return implode(', ', $parts);
    }

    public function isCritical(): bool
    {
        $criticalActions = [
            'company.deleted',
            'user.deleted',
            'document.deleted',
            'system.backup_created',
            'system.maintenance_mode',
        ];
        
        return in_array($this->action, $criticalActions);
    }

    public function isUserAction(): bool
    {
        return str_starts_with($this->action, 'user.');
    }

    public function isCompanyAction(): bool
    {
        return str_starts_with($this->action, 'company.');
    }

    public function isSystemAction(): bool
    {
        return str_starts_with($this->action, 'system.');
    }

    public function isBillingAction(): bool
    {
        return str_starts_with($this->action, 'billing.');
    }
}