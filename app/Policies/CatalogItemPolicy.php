<?php

namespace App\Policies;

use App\Domain\Catalog\CatalogItem;
use App\Domain\Identity\User;
use App\Enums\UserRole;

class CatalogItemPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->roleIn(), [UserRole::Owner, UserRole::Office, UserRole::Accountant, UserRole::Monteur], true);
    }

    public function create(User $user): bool
    {
        return $user->roleIn() === UserRole::Owner;
    }

    public function update(User $user, CatalogItem $item): bool
    {
        return $this->create($user) && $item->organization_id === $user->current_organization_id;
    }
}
