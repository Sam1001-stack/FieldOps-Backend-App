<?php

use App\Domain\Identity\User;
use App\Domain\Jobs\ServiceJob;
use App\Enums\JobStatus;
use Database\Seeders\DatabaseSeeder;

it('rejects unauthenticated datev export with 401 not 500', function () {
    $this->getJson('/api/v1/invoices/export')->assertUnauthorized();
});

it('exports datev csv with a bearer token', function () {
    $this->seed(DatabaseSeeder::class);
    $owner = User::query()->where('email', 'kevin.m@example.com')->firstOrFail();
    login($owner);

    $this->get('/api/v1/invoices/export')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('returns german validation copy instead of validation.required', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonMissing(['email' => ['validation.required']])
        ->assertSee('E-Mail', false);
});

it('returns german billing error when super admin has no tenant', function () {
    $admin = User::query()->create([
        'name' => 'Solo Admin',
        'email' => 'solo.admin@fieldops.test',
        'password' => '12345678',
        'is_super_admin' => true,
    ]);
    login($admin);

    $this->getJson('/api/v1/billing')
        ->assertStatus(422)
        ->assertJsonPath('code', 'http')
        ->assertJsonPath('message', 'Kein Mandant ausgewählt.');
});

it('blocks pending office from transitioning jobs', function () {
    $this->seed(DatabaseSeeder::class);
    $pending = User::query()->where('email', 'nina.v@example.com')->firstOrFail();
    login($pending);

    $job = ServiceJob::query()->withoutGlobalScopes()
        ->where('organization_id', $pending->current_organization_id)
        ->where('status', JobStatus::Assigned->value)
        ->firstOrFail();

    $this->postJson('/api/v1/jobs/'.$job->public_id.'/transition', ['status' => 'en_route'])
        ->assertForbidden();
});
