<?php

namespace App\Domain\Jobs;

use App\Support\BelongsToOrganization;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSignature extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    protected $fillable = ['organization_id', 'job_id', 'signer_name', 'path', 'signed_at'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }
}
