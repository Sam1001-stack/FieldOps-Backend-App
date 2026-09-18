<?php

it('exposes a public health url without auth', function () {
    config(['app.url' => 'https://fieldops-api.onrender.com']);

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('database', 'ok')
        ->assertJsonPath('url', 'https://fieldops-api.onrender.com')
        ->assertJsonStructure(['ok', 'status', 'app', 'env', 'url', 'public_url', 'database']);

    $this->getJson('/health')
        ->assertOk()
        ->assertJsonPath('ok', true);
});

it('reports whether a given backend url matches production app url', function () {
    config(['app.url' => 'https://fieldops-api.onrender.com']);

    $this->getJson('/api/v1/health?url=https://fieldops-api.onrender.com')
        ->assertOk()
        ->assertJsonPath('url_matches', true);

    $this->getJson('/api/v1/health?backendurl=https://other.example')
        ->assertOk()
        ->assertJsonPath('url_matches', false);
});
