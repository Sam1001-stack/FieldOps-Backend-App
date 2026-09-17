<?php

namespace App\Domain\Invoicing;

use App\Support\BelongsToOrganization;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    protected $fillable = ['organization_id', 'invoice_id', 'number', 'total_cents', 'reason'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
