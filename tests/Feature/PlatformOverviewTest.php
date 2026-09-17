<?php

use App\Domain\Identity\User;
use Database\Seeders\DatabaseSeeder;

it('lets the super admin see live platform activity', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
    login($admin);

    $this->getJson('/api/v1/platform/overview')
        ->assertOk()
        ->assertJsonPath('stats.organizations', 1)
        ->assertJsonPath('organizations.0.name', 'Mustermann SHK GmbH')
        ->assertJsonStructure([
            'stats' => ['jobs', 'invoices', 'revenue_cents', 'users'],
            'jobs',
            'invoices',
            'activity',
        ]);
});
