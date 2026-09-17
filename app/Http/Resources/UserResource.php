<?php

namespace App\Http\Resources;

use App\Domain\Identity\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $org = $this->currentOrganization;
        $role = $this->roleIn();
        $verification = $this->customerProfile?->verification_status;
        $officeApproval = $this->officeApprovalStatus();

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_super_admin' => $this->is_super_admin,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'large_type' => $this->large_type,
            'can_create_jobs' => $this->canCreateJobs(),
            'verification_status' => $verification?->value,
            'verification_label' => $verification?->label(),
            'office_approval_status' => $officeApproval?->value,
            'office_approval_label' => $officeApproval?->label(),
            'access_message' => $this->accessMessage(),
            'organization' => $org ? [
                'id' => $org->public_id,
                'name' => $org->name,
                'plan' => $org->plan,
                'suspended' => $org->suspended,
            ] : null,
            'organizations' => $this->when($this->is_super_admin, function () {
                return \App\Domain\Organization\Organization::query()
                    ->orderBy('name')
                    ->get(['id', 'public_id', 'name', 'plan'])
                    ->map(fn ($o) => [
                        'id' => $o->public_id,
                        'name' => $o->name,
                        'plan' => $o->plan,
                    ]);
            }),
        ];
    }

    private function accessMessage(): ?string
    {
        if ($this->canCreateJobs()) {
            return null;
        }

        return match ($this->roleIn()) {
            UserRole::Customer => 'Das Büro prüft Ihr Konto. Danach können Sie Aufträge anlegen.',
            UserRole::Office => 'Der Super-Admin muss Ihr Büro-Konto freigeben, bevor Sie Einsätze anlegen.',
            default => null,
        };
    }
}
