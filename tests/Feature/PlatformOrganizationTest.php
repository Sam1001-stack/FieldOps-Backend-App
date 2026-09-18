<?php

use App\Domain\Identity\User;
use App\Domain\Organization\Organization;
use Database\Seeders\DatabaseSeeder;

it('lets a super admin create a tenant with an owner', function () {
    $admin = User::query()->create([
        'name' => 'FieldOps Admin',
        'email' => 'admin@admin.com',
        'password' => '12345678',
        'is_super_admin' => true,
    ]);
    login($admin);

    $this->postJson('/api/v1/platform/organizations', [
        'name' => 'QA SHK GmbH',
        'city' => 'Frankfurt am Main',
        'street' => 'Werkstattstraße 1',
        'zip' => '60311',
        'owner_name' => 'Max QA',
        'owner_email' => 'max.qa@fieldops.test',
        'owner_password' => 'FieldOps!2026',
    ])
        ->assertCreated()
        ->assertJsonPath('name', 'QA SHK GmbH')
        ->assertJsonPath('owner.email', 'max.qa@fieldops.test');

    expect(Organization::query()->where('name', 'QA SHK GmbH')->exists())->toBeTrue();

    $owner = User::query()->where('email', 'max.qa@fieldops.test')->firstOrFail();
    login($owner);
    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('role', 'owner')
        ->assertJsonPath('organization.name', 'QA SHK GmbH');
});

it('forbids non super admins from creating tenants', function () {
    $this->seed(DatabaseSeeder::class);
    $owner = User::query()->where('email', 'kevin.m@example.com')->firstOrFail();
    login($owner);

    $this->postJson('/api/v1/platform/organizations', [
        'name' => 'Fremd GmbH',
        'owner_name' => 'X',
        'owner_email' => 'x@example.com',
        'owner_password' => 'FieldOps!2026',
    ])->assertForbidden();
});
