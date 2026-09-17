<?php

use App\Domain\Identity\User;
use Database\Seeders\DatabaseSeeder;

it('groups team members and returns their logs', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
    login($admin);

    $staff = $this->getJson('/api/v1/organization/members?group=staff')->assertOk()->json();
    expect(collect($staff)->pluck('role')->unique()->values()->all())->toBe(['monteur'])
        ->and(collect($staff)->pluck('email'))->toContain('ali.kaya@fieldops.test', 'julia.r@example.org');

    $office = $this->getJson('/api/v1/organization/members?group=office')->assertOk()->json();
    expect(collect($office)->pluck('role')->all())->toContain('owner', 'office', 'accountant');

    $this->getJson('/api/v1/organization/members/logs?group=staff')
        ->assertOk()
        ->assertJsonFragment(['description' => 'job.assigned']);

    $this->getJson('/api/v1/organization/members/logs?group=office')
        ->assertOk()
        ->assertJsonFragment(['description' => 'invoice.sent']);
});
