<?php

namespace App\Http\Resources;

use App\Domain\Invoicing\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'immutable' => $this->status->isImmutable(),
            'service_date' => $this->service_date?->toDateString(),
            'issued_at' => $this->issued_at?->toDateString(),
            'subtotal_cents' => $this->subtotal_cents,
            'tax_cents' => $this->tax_cents,
            'total_cents' => $this->total_cents,
            'pdf_url' => $this->pdf_path ? Storage::disk('public')->url($this->pdf_path) : null,
            'customer' => $this->whenLoaded('customer', fn () => [
                'name' => $this->customer->name,
            ]),
            'job' => $this->whenLoaded('job', fn () => [
                'id' => $this->job->public_id,
                'title' => $this->job->title,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price_cents' => $i->unit_price_cents,
                'tax_rate' => $i->tax_rate,
            ])),
            'organization' => [
                'name' => $this->organization?->settings?->legal_name ?? $this->organization?->name,
                'street' => $this->organization?->settings?->street,
                'zip' => $this->organization?->settings?->zip,
                'city' => $this->organization?->settings?->city,
                'tax_number' => $this->organization?->settings?->tax_number,
                'vat_id' => $this->organization?->settings?->vat_id,
                'iban' => $this->organization?->settings?->iban,
            ],
        ];
    }
}
