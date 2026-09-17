<?php

namespace App\Domain\Jobs;

use App\Domain\Catalog\CatalogItem;
use App\Support\BelongsToOrganization;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMaterial extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    protected $fillable = [
        'organization_id', 'job_id', 'catalog_item_id', 'quantity', 'unit_price_cents',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
