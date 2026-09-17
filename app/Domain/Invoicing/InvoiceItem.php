<?php

namespace App\Domain\Invoicing;

use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'invoice_id', 'description', 'quantity', 'unit_price_cents', 'tax_rate',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
