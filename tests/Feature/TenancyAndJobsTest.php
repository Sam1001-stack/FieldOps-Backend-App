<?php

use App\Domain\Identity\User;
use App\Domain\Jobs\ServiceJob;
use App\Enums\JobStatus;
use Database\Seeders\DatabaseSeeder;

it('denies cross-tenant job access', function () {
    $this->seed(DatabaseSeeder::class);

    $owner = User::query()->where('email', 'kevin.m@example.com')->firstOrFail();
    $job = ServiceJob::query()->withoutGlobalScopes()->firstOrFail();

    $other = User::factory()->create();
    $otherOrg = \App\Domain\Organization\Organization::query()->create(['name' => 'Andere GmbH']);
    $other->forceFill(['current_organization_id' => $otherOrg->id])->save();
    app(\App\Application\Identity\AttachMember::class)->handle($otherOrg, $other, \App\Enums\UserRole::Owner);

    login($other);

    $this->getJson('/api/v1/jobs/'.$job->public_id)->assertNotFound();
});

it('rejects illegal monteur status transitions', function () {
    $this->seed(DatabaseSeeder::class);
    $monteur = User::query()->where('email', 'ali.kaya@fieldops.test')->firstOrFail();
    login($monteur);

    $job = ServiceJob::query()->withoutGlobalScopes()
        ->where('organization_id', $monteur->current_organization_id)
        ->where('status', JobStatus::Assigned->value)
        ->whereHas('assignees', fn ($q) => $q->where('users.id', $monteur->id))
        ->firstOrFail();

    $this->postJson('/api/v1/jobs/'.$job->public_id.'/transition', ['status' => 'invoiced'])
        ->assertStatus(422);
});

it('hides other monteure jobs', function () {
    $this->seed(DatabaseSeeder::class);
    $ali = User::query()->where('email', 'ali.kaya@fieldops.test')->firstOrFail();
    login($ali);

    $ids = collect($this->getJson('/api/v1/jobs')->json('data'))->pluck('id');
    $foreign = ServiceJob::query()->withoutGlobalScopes()
        ->where('organization_id', $ali->current_organization_id)
        ->whereHas('assignees', fn ($q) => $q->where('users.id', '!=', $ali->id))
        ->whereDoesntHave('assignees', fn ($q) => $q->where('users.id', $ali->id))
        ->first();

    if ($foreign) {
        expect($ids)->not->toContain($foreign->public_id);
    }
});

it('locks sent invoices', function () {
    $this->seed(DatabaseSeeder::class);
    $owner = User::query()->where('email', 'kevin.m@example.com')->firstOrFail();
    login($owner);

    $invoice = \App\Domain\Invoicing\Invoice::query()->withoutGlobalScopes()
        ->where('status', 'sent')
        ->firstOrFail();

    $this->postJson('/api/v1/invoices/'.$invoice->public_id.'/send')
        ->assertStatus(422);
});
