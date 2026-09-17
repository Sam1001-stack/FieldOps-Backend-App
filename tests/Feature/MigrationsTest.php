<?php

use Illuminate\Support\Facades\Schema;

it('creates every FieldOps table and user column', function () {
    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('organizations'))->toBeTrue()
        ->and(Schema::hasTable('organization_settings'))->toBeTrue()
        ->and(Schema::hasTable('organization_user'))->toBeTrue()
        ->and(Schema::hasTable('customers'))->toBeTrue()
        ->and(Schema::hasTable('sites'))->toBeTrue()
        ->and(Schema::hasTable('catalog_items'))->toBeTrue()
        ->and(Schema::hasTable('service_jobs'))->toBeTrue()
        ->and(Schema::hasTable('job_assignments'))->toBeTrue()
        ->and(Schema::hasTable('invoices'))->toBeTrue()
        ->and(Schema::hasTable('invoice_items'))->toBeTrue()
        ->and(Schema::hasTable('activity_log'))->toBeTrue()
        ->and(Schema::hasTable('media'))->toBeTrue()
        ->and(Schema::hasTable('personal_access_tokens'))->toBeTrue()
        ->and(Schema::hasTable('app_notifications'))->toBeTrue()
        ->and(Schema::hasTable('content_pages'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'public_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'current_organization_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'stripe_id'))->toBeTrue()
        ->and(Schema::hasColumn('service_jobs', 'status'))->toBeTrue();
});

it('can rollback, remigrate, and reseed', function () {
    $this->artisan('migrate:rollback', ['--step' => 4])->assertSuccessful();
    expect(Schema::hasTable('service_jobs'))->toBeFalse()
        ->and(Schema::hasTable('app_notifications'))->toBeFalse();

    $this->artisan('migrate')->assertSuccessful();
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(Schema::hasTable('service_jobs'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'public_id'))->toBeTrue()
        ->and(\App\Domain\Jobs\ServiceJob::query()->exists())->toBeTrue();
});
