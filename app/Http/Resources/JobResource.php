<?php

namespace App\Http\Resources;

use App\Domain\Jobs\ServiceJob;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceJob */
class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isCustomer = $user?->roleIn() === UserRole::Customer;
        $hidePhotos = $user?->roleIn() === UserRole::Accountant
            && ! $this->organization?->settings?->accountant_can_see_photos;

        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'description' => $this->description,
            'internal_notes' => $isCustomer ? null : $this->internal_notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer_status' => $this->status->customerLabel(),
            'urgency' => $this->urgency->value,
            'urgency_label' => $this->urgency->label(),
            'gewerk' => $this->gewerk,
            'scheduled_start' => $this->scheduled_start?->timezone('Europe/Berlin')->toIso8601String(),
            'scheduled_end' => $this->scheduled_end?->timezone('Europe/Berlin')->toIso8601String(),
            'window_start' => $this->window_start?->timezone('Europe/Berlin')->toIso8601String(),
            'window_end' => $this->window_end?->timezone('Europe/Berlin')->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->public_id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
            ]),
            'site' => $this->whenLoaded('site', fn () => [
                'id' => $this->site->public_id,
                'label' => $this->site->label,
                'street' => $this->site->street,
                'zip' => $this->site->zip,
                'city' => $this->site->city,
                'lat' => $this->site->lat,
                'lng' => $this->site->lng,
                'address' => $this->site->addressLine(),
            ]),
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map(fn ($u) => [
                'id' => $u->public_id,
                'name' => $u->name,
                'phone' => $u->phone,
            ])),
            'photos' => $hidePhotos ? [] : $this->whenLoaded('photos', fn () => $this->photos->map(fn ($p) => [
                'id' => $p->public_id,
                'kind' => $p->kind->value,
                'url' => $p->signedUrl(),
            ])),
            'checklist' => $this->whenLoaded('checklistItems', fn () => $this->checklistItems->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->label,
                'done' => $c->done,
            ])),
            'materials' => $this->whenLoaded('materials', fn () => $this->materials->map(fn ($m) => [
                'id' => $m->public_id,
                'name' => $m->catalogItem?->name,
                'quantity' => $m->quantity,
                'unit_price_cents' => $user?->roleIn() === UserRole::Monteur ? null : $m->unit_price_cents,
            ])),
            'signatures' => $this->whenLoaded('signatures', fn () => $this->signatures->map(fn ($s) => [
                'id' => $s->public_id,
                'signer_name' => $s->signer_name,
                'signed_at' => $s->signed_at?->timezone('Europe/Berlin')->toIso8601String(),
            ])),
            'time_entries' => $this->whenLoaded('timeEntries', fn () => $this->timeEntries->map(fn ($t) => [
                'id' => $t->public_id,
                'started_at' => $t->started_at?->timezone('Europe/Berlin')->toIso8601String(),
                'ended_at' => $t->ended_at?->timezone('Europe/Berlin')->toIso8601String(),
                'minutes' => $t->minutes(),
            ])),
            'invoice_id' => $this->whenLoaded('invoice', fn () => $this->invoice?->public_id),
        ];
    }
}
