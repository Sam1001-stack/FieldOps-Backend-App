<?php

namespace App\Policies;

use App\Domain\Identity\User;
use App\Domain\Jobs\ServiceJob;
use App\Enums\UserRole;

class ServiceJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->roleIn() !== null;
    }

    public function view(User $user, ServiceJob $job): bool
    {
        if ($user->is_super_admin) {
            return true;
        }
        if ($job->organization_id !== $user->current_organization_id) {
            return false;
        }

        return match ($user->roleIn()) {
            UserRole::Owner, UserRole::Office => true,
            UserRole::Monteur => $job->isAssignedTo($user),
            UserRole::Customer => $job->customer?->user_id === $user->id,
            UserRole::Accountant => false,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->canCreateJobs();
    }

    public function update(User $user, ServiceJob $job): bool
    {
        if ($job->organization_id !== $user->current_organization_id) {
            return false;
        }

        return match ($user->roleIn()) {
            UserRole::Owner, UserRole::Office => true,
            UserRole::Monteur => $job->isAssignedTo($user),
            UserRole::Customer => $job->customer?->user_id === $user->id,
            default => false,
        };
    }

    public function dispatch(User $user): bool
    {
        if ($user->roleIn() === UserRole::Office) {
            return $user->isOfficeApproved();
        }

        return $user->roleIn() === UserRole::Owner;
    }

    public function invoice(User $user, ServiceJob $job): bool
    {
        return $this->dispatch($user) && $job->organization_id === $user->current_organization_id;
    }
}
