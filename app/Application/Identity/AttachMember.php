<?php

namespace App\Application\Identity;

use App\Application\Notifications\DispatchNotification;
use App\Domain\CRM\Customer;
use App\Domain\Identity\User;
use App\Domain\Organization\Organization;
use App\Enums\ApprovalStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;

final class AttachMember
{
    public function handle(Organization $organization, User $user, UserRole $role, ?User $actor = null): void
    {
        $approval = $role === UserRole::Office && ! $actor?->is_super_admin
            ? ApprovalStatus::Pending
            : ApprovalStatus::Approved;

        $organization->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role->value,
                'approval_status' => $approval->value,
                'approved_at' => $approval === ApprovalStatus::Approved ? now() : null,
                'approved_by' => $approval === ApprovalStatus::Approved ? $actor?->id : null,
            ],
        ]);

        if (! $user->current_organization_id) {
            $user->forceFill(['current_organization_id' => $organization->id])->save();
        }

        if ($role === UserRole::Customer) {
            $verifiedByStaff = $actor && (
                $actor->is_super_admin
                || $actor->hasOrgRole(UserRole::Owner, $organization)
                || $actor->isOfficeApproved($organization)
            );
            $status = $verifiedByStaff ? VerificationStatus::Verified : VerificationStatus::Pending;

            $customer = Customer::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                ],
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'verification_status' => $status,
                    'verified_at' => $status === VerificationStatus::Verified ? now() : null,
                    'verified_by' => $status === VerificationStatus::Verified ? $actor?->id : null,
                ],
            );

            if ($status === VerificationStatus::Pending && $customer->wasRecentlyCreated) {
                app(DispatchNotification::class)->toOfficeAndPlatform(
                    $organization,
                    NotificationType::CustomerRegistered,
                    'Neue Kundenregistrierung',
                    "{$user->name} ({$user->email}) wartet auf Freigabe durch das Büro.",
                    $actor,
                    ['customer_id' => $customer->public_id, 'user_id' => $user->public_id],
                );
            }
        }

        if ($role === UserRole::Office && $approval === ApprovalStatus::Pending) {
            app(DispatchNotification::class)->toPlatform(
                NotificationType::OfficePending,
                'Büro wartet auf Freigabe',
                "{$user->name} ({$user->email}) braucht Super-Admin-Freigabe bei {$organization->name}.",
                $organization,
                $actor,
                ['member_id' => $user->public_id],
            );
        }
    }
}
