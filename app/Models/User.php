<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'email',
        'password',
        'role',
        'api_token',
        'permissions',
        'is_super_admin',
        'can_impersonate',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_super_admin' => 'boolean',
            'can_impersonate' => 'boolean',
            'last_login_at' => 'datetime',
            'role' => UserRole::class,
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

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function createdApiKeys(): HasMany
    {
        return $this->hasMany(CompanyApiKey::class, 'created_by');
    }

    public function adminActions(): HasMany
    {
        return $this->hasMany(AdminAction::class, 'admin_user_id');
    }

    // Helper methods
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin || $this->role === UserRole::SUPER_ADMIN;
    }

    public function isCompanyAdmin(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role === UserRole::ADMIN;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    public function canAccessCompany(int $companyId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->company_id === $companyId;
    }
}
