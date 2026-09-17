<?php

use App\Domain\Identity\User;
use Database\Seeders\DatabaseSeeder;

it('lets the owner view billing plans', function () {
    $this->seed(DatabaseSeeder::class);
    $owner = User::query()->where('email', 'kevin.m@example.com')->firstOrFail();
    login($owner);

    $this->getJson('/api/v1/billing')
        ->assertOk()
        ->assertJsonPath('current', 'trial')
        ->assertJsonPath('configured', false);
});

it('forbids office from starting checkout', function () {
    $this->seed(DatabaseSeeder::class);
    $office = User::query()->where('email', 'emma.t@example.net')->firstOrFail();
    login($office);

    $this->postJson('/api/v1/billing/checkout', ['plan' => 'starter'])
        ->assertForbidden();
});

it('rejects checkout when Stripe is not configured', function () {
    $this->seed(DatabaseSeeder::class);
    $owner = User::query()->where('email', 'kevin.m@example.com')->firstOrFail();
    login($owner);

    $this->postJson('/api/v1/billing/checkout', ['plan' => 'starter'])
        ->assertStatus(503);
});
