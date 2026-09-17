<?php

namespace App\Domain\Jobs;

use App\Domain\CRM\Customer;
use App\Domain\CRM\Site;
use App\Domain\Identity\User;
use App\Domain\Invoicing\Invoice;
use App\Enums\JobStatus;
use App\Enums\Urgency;
use App\Support\BelongsToOrganization;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceJob extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    protected $table = 'service_jobs';

    protected $fillable = [
        'organization_id', 'customer_id', 'site_id', 'title', 'description',
        'internal_notes', 'status', 'urgency', 'gewerk',
        'scheduled_start', 'scheduled_end', 'window_start', 'window_end',
    ];

    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'urgency' => Urgency::class,
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'window_start' => 'datetime',
            'window_end' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'job_assignments', 'job_id', 'user_id')->withTimestamps();
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(JobStatusEvent::class, 'job_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(JobPhoto::class, 'job_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(JobChecklistItem::class, 'job_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(JobSignature::class, 'job_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'job_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(JobMaterial::class, 'job_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'job_id');
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees()->where('users.id', $user->id)->exists();
    }
}
