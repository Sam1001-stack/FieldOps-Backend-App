<?php

namespace App\Policies;

use App\Domain\Identity\User;
use App\Domain\Organization\Organization;
use App\Enums\UserRole;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return $user->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasOrgRole(UserRole::Owner, $organization);
    }

    public function billing(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }

    public function invite(User $user, Organization $organization, string $role): bool
    {
        if ($user->hasOrgRole(UserRole::Owner, $organization)) {
            return true;
        }

        return $user->hasOrgRole(UserRole::Office, $organization)
            && $user->isOfficeApproved($organization)
            && $role === UserRole::Monteur->value;
    }

    public function approveOffice(User $user, Organization $organization): bool
    {
        return $user->is_super_admin && ! $organization->suspended;
    }
}
