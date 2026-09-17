<?php

namespace App\Http\Api\V1;

use App\Application\Notifications\DispatchNotification;
use App\Domain\CRM\Customer;
use App\Domain\CRM\Site;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);
        $query = Customer::query()->with('sites');
        if ($request->user()->roleIn() === UserRole::Customer) {
            $query->where('user_id', $request->user()->id);
        }

        return $query->latest()->get()->map(fn (Customer $c) => $this->payload($c));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Customer::class);
        $data = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'street' => ['required', 'string'],
            'zip' => ['required', 'string'],
            'city' => ['required', 'string'],
        ]);

        $customer = Customer::query()->create([
            'organization_id' => $request->user()->current_organization_id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        $site = Site::query()->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'street' => $data['street'],
            'zip' => $data['zip'],
            'city' => $data['city'],
        ]);

        return response()->json([
            'id' => $customer->id,
            'public_id' => $customer->public_id,
            'site_id' => $site->id,
        ], 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'street' => ['nullable', 'string'],
            'zip' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
        ]);
        $customer->update(collect($data)->only(['name', 'email', 'phone'])->all());
        $site = $customer->sites()->first();
        if ($site) {
            $site->update(collect($data)->only(['street', 'zip', 'city'])->filter()->all());
        }

        return [
            'id' => $customer->id,
            'public_id' => $customer->public_id,
        ];
    }

    public function verify(Request $request, Customer $customer, DispatchNotification $notify)
    {
        $this->authorize('verify', $customer);
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
        ]);
        $status = VerificationStatus::from($data['status']);
        $customer->forceFill([
            'verification_status' => $status,
            'verified_at' => $status === VerificationStatus::Verified ? now() : null,
            'verified_by' => $request->user()->id,
        ])->save();

        $org = $request->user()->currentOrganization;
        $type = $status === VerificationStatus::Verified
            ? NotificationType::CustomerVerified
            : NotificationType::CustomerRejected;
        $title = $status === VerificationStatus::Verified ? 'Konto bestätigt' : 'Konto abgelehnt';
        $body = $status === VerificationStatus::Verified
            ? "{$customer->name} kann jetzt Aufträge in der Kunden-App anlegen."
            : "{$customer->name} wurde abgelehnt.";

        $notify->toOfficeAndPlatform($org, $type, $title, $body, $request->user(), [
            'customer_id' => $customer->public_id,
            'status' => $status->value,
        ]);

        if ($customer->user) {
            $notify->send(
                $customer->user,
                $type,
                $title,
                $status === VerificationStatus::Verified
                    ? 'Das Büro hat Ihr Konto bestätigt. Sie können jetzt Aufträge anlegen.'
                    : 'Das Büro hat Ihr Konto abgelehnt. Bitte kontaktieren Sie den Betrieb.',
                $org,
                $request->user(),
                ['customer_id' => $customer->public_id],
            );
        }

        activity()->causedBy($request->user())->performedOn($customer)->withProperties([
            'status' => $status->value,
        ])->log('customer.verified');

        return $this->payload($customer->fresh('sites'));
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('update', $customer);
        $customer->delete();

        return response()->json(['message' => 'Kunde gelöscht.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Customer $c): array
    {
        return [
            'id' => $c->id,
            'public_id' => $c->public_id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'user_id' => $c->user_id,
            'verification_status' => $c->verification_status?->value ?? VerificationStatus::Pending->value,
            'verification_label' => $c->verification_status?->label() ?? VerificationStatus::Pending->label(),
            'verified_at' => $c->verified_at?->timezone('Europe/Berlin')->toIso8601String(),
            'sites' => $c->sites->map(fn (Site $s) => [
                'id' => $s->id,
                'public_id' => $s->public_id,
                'address' => $s->addressLine(),
                'street' => $s->street,
                'zip' => $s->zip,
                'city' => $s->city,
            ]),
        ];
    }
}
