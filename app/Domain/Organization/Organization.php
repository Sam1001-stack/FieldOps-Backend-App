<?php

namespace App\Domain\Organization;

use App\Domain\Identity\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'name', 'plan', 'trial_ends_at', 'suspended', 'jobs_this_month', 'storage_mb_used',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'suspended' => 'boolean',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(OrganizationSetting::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'approval_status', 'approved_at', 'approved_by')
            ->withTimestamps();
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(\App\Domain\Catalog\CatalogItem::class);
    }

    public function planLimits(): array
    {
        return match ($this->plan) {
            'team' => ['users' => 25, 'jobs' => 500, 'storage_mb' => 10240],
            'starter' => ['users' => 5, 'jobs' => 80, 'storage_mb' => 2048],
            default => ['users' => 8, 'jobs' => 50, 'storage_mb' => 1024],
        };
    }
}
