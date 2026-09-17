<?php

namespace App\Domain\Jobs;

use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobChecklistItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'job_id', 'label', 'done', 'position'];

    protected function casts(): array
    {
        return ['done' => 'boolean'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }
}
