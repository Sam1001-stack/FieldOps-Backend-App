<?php

namespace App\Domain\Jobs;

use App\Enums\PhotoKind;
use App\Support\BelongsToOrganization;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class JobPhoto extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    protected $fillable = [
        'organization_id', 'job_id', 'uploaded_by', 'kind', 'path', 'pending_sync',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PhotoKind::class,
            'pending_sync' => 'boolean',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }

    public function signedUrl(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
