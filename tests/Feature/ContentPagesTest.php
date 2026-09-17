<?php

use App\Domain\Content\ContentPage;
use App\Domain\Identity\User;
use Database\Seeders\DatabaseSeeder;

it('publishes content pages for both mobile apps', function () {
    $this->seed(DatabaseSeeder::class);

    $list = $this->getJson('/api/v1/content?audience=customer')->assertOk()->json();
    expect(collect($list)->pluck('slug'))->toContain('privacy', 'faq', 'imprint', 'terms', 'about', 'support');

    $field = $this->getJson('/api/v1/content?audience=field')->assertOk()->json();
    expect(collect($field)->pluck('slug'))->toContain('privacy', 'faq');

    $privacy = $this->getJson('/api/v1/content/privacy')->assertOk()->json();
    expect($privacy['title'])->toBe('Datenschutz')
        ->and($privacy['body'])->toContain('DSGVO');
});

it('lets only super admin edit content pages', function () {
    $this->seed(DatabaseSeeder::class);

    $office = User::query()->where('email', 'emma.t@example.net')->firstOrFail();
    login($office);
    $this->putJson('/api/v1/platform/content/faq', [
        'title' => 'FAQ intern',
        'body' => 'Nicht erlaubt',
        'audience' => 'all',
    ])->assertForbidden();

    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
    login($admin);
    $this->getJson('/api/v1/platform/content')->assertOk()->assertJsonFragment(['slug' => 'privacy']);
    $this->putJson('/api/v1/platform/content/faq', [
        'title' => 'Häufige Fragen (aktualisiert)',
        'body' => "## Neue Frage\nAntwort vom Super-Admin.",
        'audience' => 'all',
        'published' => true,
    ])->assertOk()->assertJsonPath('title', 'Häufige Fragen (aktualisiert)');

    expect(ContentPage::query()->where('slug', 'faq')->value('body'))->toContain('Neue Frage');
    expect($this->getJson('/api/v1/content/faq')->json('title'))->toBe('Häufige Fragen (aktualisiert)');
});
