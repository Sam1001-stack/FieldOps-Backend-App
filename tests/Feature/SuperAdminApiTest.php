<?php

use App\Domain\Identity\User;
use App\Enums\UserRole;
use Database\Seeders\DatabaseSeeder;

it('creates the first super admin without auth', function () {
    $this->postJson('/api/v1/auth/super-admin', [
        'email' => 'admin@admin.com',
        'password' => '12345678',
        'role' => UserRole::SuperAdmin->value,
    ])
        ->assertCreated()
        ->assertJsonPath('user.email', 'admin@admin.com')
        ->assertJsonPath('user.role', UserRole::SuperAdmin->value)
        ->assertJsonPath('user.is_super_admin', true)
        ->assertJsonStructure(['token', 'user']);

    expect(User::query()->where('email', 'admin@admin.com')->first()->is_super_admin)->toBeTrue();
});

it('rejects a non super_admin role', function () {
    $this->postJson('/api/v1/auth/super-admin', [
        'email' => 'office@example.com',
        'password' => '12345678',
        'role' => UserRole::Office->value,
    ])->assertStatus(422);
});

it('blocks a second public super admin once one exists', function () {
    $this->seed(DatabaseSeeder::class);

    $this->postJson('/api/v1/auth/super-admin', [
        'email' => 'admin2@admin.com',
        'password' => '12345678',
        'role' => UserRole::SuperAdmin->value,
    ])->assertForbidden();
});

it('lets an existing super admin create another', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
    login($admin);

    $this->postJson('/api/v1/auth/super-admin', [
        'email' => 'platform2@fieldops.test',
        'password' => '12345678',
        'role' => UserRole::SuperAdmin->value,
        'name' => 'Zweiter Admin',
    ])
        ->assertCreated()
        ->assertJsonPath('user.email', 'platform2@fieldops.test')
        ->assertJsonPath('user.is_super_admin', true);
});
