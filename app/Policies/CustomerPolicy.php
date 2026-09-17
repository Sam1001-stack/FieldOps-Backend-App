<?php

namespace App\Policies;

use App\Domain\CRM\Customer;
use App\Domain\Identity\User;
use App\Enums\UserRole;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->roleIn(), [UserRole::Owner, UserRole::Office, UserRole::Monteur, UserRole::Customer], true);
    }

    public function view(User $user, Customer $customer): bool
    {
        if ($customer->organization_id !== $user->current_organization_id) {
            return false;
        }

        return match ($user->roleIn()) {
            UserRole::Owner, UserRole::Office => true,
            UserRole::Customer => $customer->user_id === $user->id,
            UserRole::Monteur => $customer->jobs()->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))->exists(),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        if ($user->roleIn() === UserRole::Owner) {
            return true;
        }

        return $user->roleIn() === UserRole::Office && $user->isOfficeApproved();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->create($user) && $customer->organization_id === $user->current_organization_id;
    }

    public function verify(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }
}
