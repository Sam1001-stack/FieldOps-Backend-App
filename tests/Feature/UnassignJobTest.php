<?php

use App\Domain\Identity\User;
use App\Domain\Jobs\ServiceJob;
use App\Enums\JobStatus;
use Database\Seeders\DatabaseSeeder;

it('lets office drop an assigned job back to unassigned', function () {
    $this->seed(DatabaseSeeder::class);
    $office = User::query()->where('email', 'emma.t@example.net')->firstOrFail();
    login($office);

    $job = ServiceJob::query()->withoutGlobalScopes()
        ->where('organization_id', $office->current_organization_id)
        ->where('status', JobStatus::Assigned->value)
        ->whereHas('assignees')
        ->firstOrFail();

    $this->postJson('/api/v1/jobs/'.$job->public_id.'/unassign')
        ->assertOk()
        ->assertJsonPath('status', JobStatus::Scheduled->value)
        ->assertJsonPath('assignees', []);

    expect($job->fresh()->assignees()->count())->toBe(0)
        ->and($job->fresh()->status)->toBe(JobStatus::Scheduled);
});

it('rejects unassign once the monteur is en route', function () {
    $this->seed(DatabaseSeeder::class);
    $office = User::query()->where('email', 'emma.t@example.net')->firstOrFail();
    login($office);

    $job = ServiceJob::query()->withoutGlobalScopes()
        ->where('organization_id', $office->current_organization_id)
        ->where('status', JobStatus::EnRoute->value)
        ->whereHas('assignees')
        ->firstOrFail();

    $this->postJson('/api/v1/jobs/'.$job->public_id.'/unassign')
        ->assertStatus(422)
        ->assertJsonPath('code', 'domain');
});
