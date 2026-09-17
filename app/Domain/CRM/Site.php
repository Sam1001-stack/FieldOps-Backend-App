<?php

namespace App\Domain\CRM;

use App\Support\BelongsToOrganization;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Site extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    protected $fillable = [
        'organization_id', 'customer_id', 'label', 'street', 'zip', 'city', 'lat', 'lng',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function addressLine(): string
    {
        return trim("{$this->street}, {$this->zip} {$this->city}");
    }
}
