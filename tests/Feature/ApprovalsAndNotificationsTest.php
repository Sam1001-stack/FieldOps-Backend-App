<?php

use App\Domain\CRM\Customer;
use App\Domain\Identity\User;
use App\Domain\Jobs\ServiceJob;
use App\Domain\Notifications\AppNotification;
use App\Enums\ApprovalStatus;
use App\Enums\JobStatus;
use App\Enums\VerificationStatus;
use Database\Seeders\DatabaseSeeder;

it('blocks unverified customers from creating jobs until office confirms', function () {
    $this->seed(DatabaseSeeder::class);
    $orgId = \App\Domain\Organization\Organization::query()->firstOrFail()->public_id;

    $register = $this->postJson('/api/v1/auth/register', [
        'name' => 'Neu Kunde',
        'email' => 'neu.kunde@example.com',
        'password' => 'FieldOps!2026',
        'phone' => '+49 69 111',
        'street' => 'Neue Straße 1',
        'zip' => '60311',
        'city' => 'Frankfurt am Main',
        'organization_id' => $orgId,
    ])->assertCreated();

    $token = $register->json('token');
    expect($register->json('user.can_create_jobs'))->toBeFalse();
    expect($register->json('user.verification_status'))->toBe(VerificationStatus::Pending->value);

    $this->withToken($token)->postJson('/api/v1/jobs', [
        'title' => 'Heizung aus',
        'urgency' => 'notdienst',
    ])->assertForbidden();

    $office = User::query()->where('email', 'emma.t@example.net')->firstOrFail();
    login($office);

    $customer = Customer::query()->where('email', 'neu.kunde@example.com')->firstOrFail();
    expect(AppNotification::query()->where('user_id', $office->id)->where('type', 'customer.registered')->exists())->toBeTrue();

    $this->postJson('/api/v1/customers/'.$customer->public_id.'/verify', [
        'status' => 'verified',
    ])->assertOk();

    $portal = User::query()->where('email', 'neu.kunde@example.com')->firstOrFail();
    login($portal);

    $this->postJson('/api/v1/jobs', [
        'title' => 'Heizung aus',
        'description' => 'Keine Wärme seit heute früh.',
        'urgency' => 'notdienst',
        'street' => 'Neue Straße 1',
        'zip' => '60311',
        'city' => 'Frankfurt am Main',
    ])->assertCreated();

    expect(ServiceJob::query()->where('title', 'Heizung aus')->exists())->toBeTrue();
    expect(AppNotification::query()->where('user_id', $office->id)->where('type', 'job.created')->exists())->toBeTrue();
});

it('blocks office from creating jobs until super admin approves', function () {
    $this->seed(DatabaseSeeder::class);
    $pending = User::query()->where('email', 'nina.v@example.com')->firstOrFail();
    login($pending);

    expect($pending->officeApprovalStatus()?->value)->toBe(ApprovalStatus::Pending->value);

    $customer = Customer::query()->where('organization_id', $pending->current_organization_id)->firstOrFail();
    $site = $customer->sites()->first();

    $this->postJson('/api/v1/jobs', [
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'title' => 'Büro ohne Freigabe',
    ])->assertForbidden();

    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
    login($admin);

    $this->postJson('/api/v1/organization/members/'.$pending->public_id.'/approve', [
        'status' => 'approved',
    ])->assertOk();

    login($pending->fresh());
    $this->postJson('/api/v1/jobs', [
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'title' => 'Büro nach Freigabe',
    ])->assertCreated();
});

it('notifies office and platform when staff changes job status', function () {
    $this->seed(DatabaseSeeder::class);
    $monteur = User::query()->where('email', 'ali.kaya@fieldops.test')->firstOrFail();
    login($monteur);

    $job = ServiceJob::query()->withoutGlobalScopes()
        ->where('organization_id', $monteur->current_organization_id)
        ->where('status', JobStatus::Assigned->value)
        ->whereHas('assignees', fn ($q) => $q->where('users.id', $monteur->id))
        ->firstOrFail();

    $this->postJson('/api/v1/jobs/'.$job->public_id.'/transition', ['status' => 'en_route'])
        ->assertOk();

    $office = User::query()->where('email', 'emma.t@example.net')->firstOrFail();
    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();

    expect(AppNotification::query()->where('user_id', $office->id)->where('type', 'job.status')->exists())->toBeTrue();
    expect(AppNotification::query()->where('user_id', $admin->id)->where('type', 'job.status')->exists())->toBeTrue();
});

it('notifies the assigned monteur so the field app can show the job', function () {
    $this->seed(DatabaseSeeder::class);
    $office = User::query()->where('email', 'emma.t@example.net')->firstOrFail();
    $jonas = User::query()->where('email', 'julia.r@example.org')->firstOrFail();
    login($office);

    $job = ServiceJob::query()->withoutGlobalScopes()
        ->where('organization_id', $office->current_organization_id)
        ->where('status', JobStatus::Scheduled->value)
        ->whereDoesntHave('assignees')
        ->firstOrFail();

    $this->postJson('/api/v1/jobs/'.$job->public_id.'/assign', [
        'monteur_id' => $jonas->public_id,
    ])->assertOk();

    $note = AppNotification::query()
        ->where('user_id', $jonas->id)
        ->where('type', 'job.assigned')
        ->latest('id')
        ->first();

    expect($note)->not->toBeNull()
        ->and($note?->title)->toBe('Neuer Einsatz')
        ->and($note?->data['job_id'] ?? null)->toBe($job->public_id);

    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
    $adminNote = AppNotification::query()
        ->where('user_id', $admin->id)
        ->where('type', 'job.assigned')
        ->latest('id')
        ->first();

    expect($adminNote)->not->toBeNull()
        ->and($adminNote?->title)->toBe('Büro hat Einsatz zugewiesen')
        ->and($adminNote?->body)->toContain($office->name)
        ->and($adminNote?->body)->toContain($jonas->name)
        ->and($adminNote?->data['job_id'] ?? null)->toBe($job->public_id);

    login($admin);
    $adminFeed = collect($this->getJson('/api/v1/notifications')->json());
    expect($adminFeed->contains(
        fn ($row) => ($row['type'] ?? null) === 'job.assigned'
            && str_contains((string) ($row['body'] ?? ''), $jonas->name),
    ))->toBeTrue();

    login($jonas);
    $feed = collect($this->getJson('/api/v1/notifications')->json());
    expect($feed->contains(fn ($row) => ($row['type'] ?? null) === 'job.assigned' && ($row['unread'] ?? false)))->toBeTrue();
});
