<?php

namespace App\Domain\Identity;

use App\Domain\CRM\Customer;
use App\Domain\Jobs\ServiceJob;
use App\Domain\Organization\Organization;
use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Support\HasPublicUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\CausesActivity;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable, CausesActivity, HasApiTokens, HasFactory, HasPublicUuid, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'is_super_admin',
        'current_organization_id', 'avatar_path', 'large_type',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'large_type' => 'boolean',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role', 'approval_status', 'approved_at', 'approved_by')
            ->withTimestamps();
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function customerProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function roleIn(?Organization $organization = null): ?UserRole
    {
        if ($this->is_super_admin) {
            return UserRole::SuperAdmin;
        }

        $org = $organization ?? $this->currentOrganization;
        if (! $org) {
            return null;
        }

        $pivot = $this->organizations()->where('organizations.id', $org->id)->first()?->pivot;

        return $pivot?->role ? UserRole::from($pivot->role) : null;
    }

    public function hasOrgRole(UserRole $role, ?Organization $organization = null): bool
    {
        return $this->roleIn($organization) === $role;
    }

    public function assignedJobs(): BelongsToMany
    {
        return $this->belongsToMany(ServiceJob::class, 'job_assignments', 'user_id', 'job_id');
    }

    public function officeApprovalStatus(?Organization $organization = null): ?ApprovalStatus
    {
        if ($this->roleIn($organization) !== UserRole::Office) {
            return null;
        }

        $raw = $this->organizationPivot($organization)?->approval_status;

        return ApprovalStatus::tryFrom((string) $raw) ?? ApprovalStatus::Pending;
    }

    public function isOfficeApproved(?Organization $organization = null): bool
    {
        if ($this->is_super_admin || $this->roleIn($organization) === UserRole::Owner) {
            return true;
        }

        return $this->officeApprovalStatus($organization) === ApprovalStatus::Approved;
    }

    public function canCreateJobs(?Organization $organization = null): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return match ($this->roleIn($organization)) {
            UserRole::Owner => true,
            UserRole::Office => $this->isOfficeApproved($organization),
            UserRole::Customer => $this->customerProfile?->verification_status === VerificationStatus::Verified,
            default => false,
        };
    }

    public function organizationPivot(?Organization $organization = null): mixed
    {
        $org = $organization ?? $this->currentOrganization;
        if (! $org) {
            return null;
        }

        $match = $this->relationLoaded('organizations')
            ? $this->organizations->firstWhere('id', $org->id)
            : $this->organizations()->where('organizations.id', $org->id)->first();

        return $match?->pivot;
    }
}
