<?php

namespace App\Domain\Jobs;

use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobStatusEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'job_id', 'user_id', 'from_status', 'to_status'];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }
}
