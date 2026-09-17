<?php

namespace App\Domain\Jobs;

use App\Support\BelongsToOrganization;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    protected $fillable = ['organization_id', 'job_id', 'user_id', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }

    public function minutes(): int
    {
        if (! $this->ended_at) {
            return (int) $this->started_at->diffInMinutes(now());
        }

        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }
}
