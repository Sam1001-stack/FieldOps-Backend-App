<?php

namespace App\Domain\Organization;

use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSetting extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'legal_name', 'street', 'zip', 'city', 'country',
        'tax_number', 'vat_id', 'iban', 'bic', 'bank_name', 'invoice_prefix',
        'next_invoice_number', 'logo_path', 'photo_retention_months', 'accountant_can_see_photos',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
