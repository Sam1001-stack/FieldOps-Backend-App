<?php

use Database\Seeders\DatabaseSeeder;

it('logs in from the office spa origin without a csrf cookie', function () {
    $this->seed(DatabaseSeeder::class);

    $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/login',
    ])->postJson('/api/v1/auth/login', [
        'email' => 'admin@admin.com',
        'password' => '12345678',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user'])
        ->assertJsonPath('user.email', 'admin@admin.com');
});

it('logs in with a bearer-style json request and no cookies', function () {
    $this->seed(DatabaseSeeder::class);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'kevin.m@example.com',
        'password' => 'FieldOps!2026',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user']);
});
