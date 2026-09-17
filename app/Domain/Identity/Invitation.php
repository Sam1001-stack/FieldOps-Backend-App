<?php

namespace App\Domain\Identity;

use App\Domain\Organization\Organization;
use App\Enums\UserRole;
use App\Support\BelongsToOrganization;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    protected $fillable = [
        'organization_id', 'invited_by', 'email', 'role', 'token', 'accepted_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function issue(Organization $org, User $by, string $email, UserRole $role): self
    {
        return self::query()->create([
            'organization_id' => $org->id,
            'invited_by' => $by->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(48),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
