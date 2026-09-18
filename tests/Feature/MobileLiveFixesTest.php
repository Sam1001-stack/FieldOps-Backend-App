<?php

use App\Domain\Content\ContentPage;
use App\Domain\Identity\User;
use App\Domain\Organization\Organization;
use Database\Seeders\DatabaseSeeder;

it('lists unsuspended organizations for customer register', function () {
    $this->seed(DatabaseSeeder::class);

    $this->getJson('/api/v1/organizations')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Mustermann SHK GmbH']);
});

it('requires organization_id on customer register', function () {
    $this->seed(DatabaseSeeder::class);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ohne Betrieb',
        'email' => 'ohne.betrieb@example.com',
        'password' => 'FieldOps!2026',
        'street' => 'Test 1',
        'zip' => '60311',
        'city' => 'Frankfurt',
    ])->assertStatus(422);

    $orgId = Organization::query()->firstOrFail()->public_id;
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Mit Betrieb',
        'email' => 'mit.betrieb@example.com',
        'password' => 'FieldOps!2026',
        'street' => 'Test 1',
        'zip' => '60311',
        'city' => 'Frankfurt',
        'organization_id' => $orgId,
    ])->assertCreated();
});

it('returns german unauthorized message', function () {
    $this->seed(DatabaseSeeder::class);
    $customer = User::query()->where('email', 'james.b@example.com')->firstOrFail();
    login($customer);

    $this->getJson('/api/v1/invoices/export')
        ->assertForbidden()
        ->assertJsonPath('message', 'Keine Berechtigung für diese Aktion.');
});

it('lets super admin seed cms defaults when empty', function () {
    ContentPage::query()->delete();
    $admin = User::query()->create([
        'name' => 'CMS Admin',
        'email' => 'cms.admin@fieldops.test',
        'password' => '12345678',
        'is_super_admin' => true,
    ]);
    login($admin);

    expect(ContentPage::query()->count())->toBe(0);

    $this->postJson('/api/v1/platform/content/seed')
        ->assertOk();

    expect(ContentPage::query()->where('published', true)->count())->toBeGreaterThan(0);
    $this->getJson('/api/v1/content?audience=customer')->assertOk()->assertJsonFragment(['slug' => 'privacy']);
});
