<?php

namespace App\Domain\Invoicing;

use App\Domain\CRM\Customer;
use App\Domain\Jobs\ServiceJob;
use App\Enums\InvoiceStatus;
use App\Support\BelongsToOrganization;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    protected $fillable = [
        'organization_id', 'job_id', 'customer_id', 'number', 'status', 'service_date',
        'issued_at', 'subtotal_cents', 'tax_cents', 'total_cents', 'pdf_path', 'sent_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'service_date' => 'date',
            'issued_at' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }
}
