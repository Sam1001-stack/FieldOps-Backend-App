<?php

namespace App\Policies;

use App\Domain\Identity\User;
use App\Domain\Invoicing\Invoice;
use App\Enums\UserRole;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->roleIn(), [UserRole::Owner, UserRole::Office, UserRole::Accountant, UserRole::Customer], true);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($invoice->organization_id !== $user->current_organization_id) {
            return false;
        }

        return match ($user->roleIn()) {
            UserRole::Owner, UserRole::Office, UserRole::Accountant => true,
            UserRole::Customer => $invoice->customer?->user_id === $user->id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return in_array($user->roleIn(), [UserRole::Owner, UserRole::Office], true);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->create($user) && $invoice->organization_id === $user->current_organization_id;
    }

    public function export(User $user): bool
    {
        return in_array($user->roleIn(), [UserRole::Owner, UserRole::Accountant], true);
    }
}
