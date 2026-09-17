<?php

namespace App\Domain\Catalog;

use App\Support\BelongsToOrganization;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class CatalogItem extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    protected $fillable = [
        'organization_id', 'kind', 'name', 'unit', 'unit_price_cents', 'tax_rate', 'is_hourly_rate',
    ];

    protected function casts(): array
    {
        return ['is_hourly_rate' => 'boolean'];
    }
}
