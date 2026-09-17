<?php

use App\Domain\Identity\User;
use Database\Seeders\DatabaseSeeder;

it('lets super admin create update and delete tenant records', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
    login($admin);

    $this->getJson('/api/v1/jobs')->assertOk();
    $this->getJson('/api/v1/customers')->assertOk();
    $this->getJson('/api/v1/organization/members')->assertOk();
    $this->getJson('/api/v1/invoices')->assertOk();
    $this->getJson('/api/v1/billing')->assertOk();
    $this->getJson('/api/v1/dispatch/board')->assertOk();

    $created = $this->postJson('/api/v1/customers', [
        'name' => 'Test GmbH',
        'email' => 'testgmbh@example.com',
        'street' => 'Testweg 1',
        'zip' => '60311',
        'city' => 'Frankfurt am Main',
    ])->assertCreated();

    $this->postJson('/api/v1/jobs', [
        'customer_id' => $created->json('id'),
        'site_id' => $created->json('site_id'),
        'title' => 'Super-Admin Einsatz',
        'urgency' => 'normal',
    ])->assertCreated();

    $jobId = $this->postJson('/api/v1/jobs', [
        'customer_id' => $created->json('id'),
        'site_id' => $created->json('site_id'),
        'title' => 'Zum Löschen',
        'urgency' => 'normal',
    ])->json('id');

    $this->deleteJson('/api/v1/jobs/'.$jobId)->assertOk();
});
