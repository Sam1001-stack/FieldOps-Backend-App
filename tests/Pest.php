<?php

use App\Domain\Identity\User;
use App\Domain\Jobs\JobStatusMachine;
use App\Domain\Jobs\ServiceJob;
use App\Domain\Organization\Organization;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\User as UserModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function login(User $user): User
{
    test()->actingAs($user, 'sanctum');
    if ($user->current_organization_id) {
        app()->instance('current_organization_id', (int) $user->current_organization_id);
        setPermissionsTeamId($user->current_organization_id);
    }

    return $user;
}
