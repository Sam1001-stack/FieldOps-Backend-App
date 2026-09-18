<?php

namespace App\Http\Api\V1;

use App\Domain\Organization\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /** Public list for customer self-registration (Betrieb picker). */
    public function publicIndex()
    {
        return Organization::query()
            ->where('suspended', false)
            ->orderBy('name')
            ->get(['public_id', 'name'])
            ->map(fn (Organization $org) => [
                'id' => $org->public_id,
                'name' => $org->name,
            ])
            ->values();
    }

    public function settings(Request $request)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 422, 'Kein Mandant ausgewählt.');
        $this->authorize('view', $org);

        return $org->settings;
    }

    public function updateSettings(Request $request)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 422, 'Kein Mandant ausgewählt.');
        $this->authorize('update', $org);
        $data = $request->validate([
            'legal_name' => ['nullable', 'string'],
            'street' => ['nullable', 'string'],
            'zip' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string'],
            'vat_id' => ['nullable', 'string'],
            'iban' => ['nullable', 'string'],
            'bic' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string'],
            'invoice_prefix' => ['nullable', 'string', 'max:8'],
        ]);
        $org->settings->update($data);
        activity()->causedBy($request->user())->performedOn($org)->log('org.settings');

        return $org->settings->fresh();
    }
}
