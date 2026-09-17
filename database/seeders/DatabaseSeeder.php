<?php

namespace Database\Seeders;

use App\Application\Identity\AttachMember;
use App\Application\Invoicing\CreateInvoiceFromJob;
use App\Application\Invoicing\SendInvoice;
use App\Application\Jobs\TransitionJobStatus;
use App\Domain\Catalog\CatalogItem;
use App\Domain\CRM\Customer;
use App\Domain\CRM\Site;
use App\Domain\Identity\User;
use App\Domain\Invoicing\Invoice;
use App\Domain\Jobs\JobMaterial;
use App\Domain\Jobs\JobPhoto;
use App\Domain\Jobs\ServiceJob;
use App\Domain\Jobs\TimeEntry;
use App\Domain\Organization\Organization;
use App\Domain\Organization\OrganizationSetting;
use App\Enums\JobStatus;
use App\Enums\PhotoKind;
use App\Enums\Urgency;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $attach = app(AttachMember::class);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'FieldOps Admin',
                'password' => '12345678',
                'is_super_admin' => true,
            ],
        );

        $this->call(ContentPageSeeder::class);

        $org = Organization::query()->firstOrCreate(
            ['name' => 'Mustermann SHK GmbH'],
            [
                'plan' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ],
        );

        OrganizationSetting::query()->firstOrCreate(
            ['organization_id' => $org->id],
            [
                'legal_name' => 'Mustermann SHK GmbH',
                'street' => 'Werkstattstraße 12',
                'zip' => '60311',
                'city' => 'Frankfurt am Main',
                'tax_number' => '045 226 00013',
                'vat_id' => 'DE123456789',
                'iban' => 'DE89 3704 0044 0532 0130 00',
                'bic' => 'COBADEFFXXX',
                'bank_name' => 'Commerzbank',
                'invoice_prefix' => 'RE',
                'next_invoice_number' => 1,
            ],
        );

        $users = [
            ['name' => 'Max Mustermann', 'email' => 'kevin.m@example.com', 'role' => UserRole::Owner],
            ['name' => 'Clara Büro', 'email' => 'emma.t@example.net', 'role' => UserRole::Office],
            ['name' => 'Ali Kaya', 'email' => 'ali.kaya@fieldops.test', 'role' => UserRole::Monteur, 'phone' => '+49 171 1000001'],
            ['name' => 'Jonas Weber', 'email' => 'julia.r@example.org', 'role' => UserRole::Monteur, 'phone' => '+49 171 1000002'],
            ['name' => 'Anna Schmidt', 'email' => 'james.b@example.com', 'role' => UserRole::Customer, 'phone' => '+49 69 123456'],
            ['name' => 'Lea Steuer', 'email' => 'leo.a@example.org', 'role' => UserRole::Accountant],
        ];

        $byEmail = [];
        foreach ($users as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'phone' => $row['phone'] ?? null,
                    'password' => Hash::make('FieldOps!2026'),
                    'current_organization_id' => $org->id,
                ],
            );
            $attach->handle($org, $user, $row['role'], $admin);
            $byEmail[$row['email']] = $user;
        }

        $pendingOffice = User::query()->updateOrCreate(
            ['email' => 'nina.v@example.com'],
            [
                'name' => 'Nina Warte',
                'password' => Hash::make('FieldOps!2026'),
                'phone' => '+49 69 555000',
                'current_organization_id' => $org->id,
            ],
        );
        $attach->handle($org, $pendingOffice, UserRole::Office);

        app()->instance('current_organization_id', $org->id);
        setPermissionsTeamId($org->id);

        if (ServiceJob::query()->where('organization_id', $org->id)->exists()) {
            return;
        }

        $hourly = CatalogItem::query()->create([
            'organization_id' => $org->id,
            'kind' => 'labor',
            'name' => 'Monteurstunde',
            'unit' => 'h',
            'unit_price_cents' => 8900,
            'tax_rate' => 19,
            'is_hourly_rate' => true,
        ]);
        $dichtung = CatalogItem::query()->create([
            'organization_id' => $org->id,
            'kind' => 'material',
            'name' => 'Dichtung 3/4"',
            'unit' => 'Stk',
            'unit_price_cents' => 450,
            'tax_rate' => 19,
        ]);
        CatalogItem::query()->create([
            'organization_id' => $org->id,
            'kind' => 'material',
            'name' => 'Brennerdüse',
            'unit' => 'Stk',
            'unit_price_cents' => 1890,
            'tax_rate' => 19,
        ]);

        $customerUser = $byEmail['james.b@example.com'];
        $customers = [];
        $anna = Customer::query()
            ->where('organization_id', $org->id)
            ->where('user_id', $customerUser->id)
            ->first();
        if ($anna) {
            $annaSite = $anna->sites()->first() ?: Site::query()->create([
                'organization_id' => $org->id,
                'customer_id' => $anna->id,
                'street' => 'Musterstraße 12',
                'zip' => '60313',
                'city' => 'Frankfurt am Main',
                'lat' => 50.1109,
                'lng' => 8.6821,
            ]);
            $customers[] = [$anna, $annaSite];
        }
        foreach ([
            ['Hausverwaltung Main', 'hv@example.com', 'Zeil 40', '60313', 'Frankfurt am Main', null],
            ['Lukas Keller', 'keller@example.com', 'Berger Straße 88', '60316', 'Frankfurt am Main', null],
        ] as $c) {
            $cust = Customer::query()->create([
                'organization_id' => $org->id,
                'user_id' => $c[5],
                'name' => $c[0],
                'email' => $c[1],
                'phone' => '+49 69 0000',
                'verification_status' => \App\Enums\VerificationStatus::Verified,
                'verified_at' => now(),
            ]);
            $site = Site::query()->create([
                'organization_id' => $org->id,
                'customer_id' => $cust->id,
                'street' => $c[2],
                'zip' => $c[3],
                'city' => $c[4],
                'lat' => 50.1109,
                'lng' => 8.6821,
            ]);
            $customers[] = [$cust, $site];
        }

        $ali = $byEmail['ali.kaya@fieldops.test'];
        $jonas = $byEmail['julia.r@example.org'];
        $owner = $byEmail['kevin.m@example.com'];
        $today = now('Europe/Berlin')->startOfDay()->addHours(8);

        $defs = [
            ['Heizung ausgefallen', JobStatus::Assigned, Urgency::Emergency, $ali, 0],
            ['Wartung Therme', JobStatus::EnRoute, Urgency::Normal, $ali, 1],
            ['Undichte Armatur', JobStatus::OnSite, Urgency::Normal, $jonas, 2],
            ['Heizkörper entlüften', JobStatus::Scheduled, Urgency::Normal, null, 0],
            ['Notdienst Wasseruhr', JobStatus::Draft, Urgency::Emergency, null, 1],
            ['Filterwechsel', JobStatus::WaitingParts, Urgency::Normal, $jonas, 2],
            ['Abnahme nach Einbau', JobStatus::Completed, Urgency::Normal, $ali, 0],
            ['Jahreswartung Vertrag', JobStatus::Completed, Urgency::Normal, $jonas, 1],
            ['Leckage Keller', JobStatus::Cancelled, Urgency::Emergency, null, 2],
            ['Zirkulationspumpe', JobStatus::Assigned, Urgency::Normal, $jonas, 0],
        ];

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $jobs = [];
        foreach ($defs as $i => $def) {
            [$title, $status, $urgency, $who, $custIndex] = $def;
            [$cust, $site] = $customers[$custIndex];
            $job = ServiceJob::query()->create([
                'organization_id' => $org->id,
                'customer_id' => $cust->id,
                'site_id' => $site->id,
                'title' => $title,
                'description' => 'SHK Einsatz aus dem Mustermann-Alltag.',
                'status' => $status,
                'urgency' => $urgency,
                'gewerk' => 'shk',
                'scheduled_start' => $today->copy()->addHours($i % 8),
                'scheduled_end' => $today->copy()->addHours(($i % 8) + 2),
                'window_start' => $today->copy()->addHours($i % 8),
                'window_end' => $today->copy()->addHours(($i % 8) + 2),
            ]);
            if ($who) {
                $job->assignees()->attach($who->id, ['organization_id' => $org->id]);
            }
            $path = "jobs/{$job->id}/seed.png";
            Storage::disk('public')->put($path, $png);
            JobPhoto::query()->create([
                'organization_id' => $org->id,
                'job_id' => $job->id,
                'kind' => PhotoKind::Before,
                'path' => $path,
            ]);
            $jobs[] = $job;
        }

        $completedA = $jobs[6];
        $completedB = $jobs[7];
        foreach ([$completedA, $completedB] as $job) {
            TimeEntry::query()->create([
                'organization_id' => $org->id,
                'job_id' => $job->id,
                'user_id' => $job->assignees()->first()->id,
                'started_at' => now()->subHours(2),
                'ended_at' => now()->subHour(),
            ]);
            JobMaterial::query()->create([
                'organization_id' => $org->id,
                'job_id' => $job->id,
                'catalog_item_id' => $dichtung->id,
                'quantity' => 2,
                'unit_price_cents' => $dichtung->unit_price_cents,
            ]);
        }

        $createInv = app(CreateInvoiceFromJob::class);
        $sendInv = app(SendInvoice::class);
        $draft = $createInv->handle($completedA->fresh(['timeEntries', 'materials.catalogItem', 'organization.settings']), $owner);
        $sent = $createInv->handle($completedB->fresh(['timeEntries', 'materials.catalogItem', 'organization.settings']), $owner);
        $sendInv->handle($sent, $owner);

        $org->update(['jobs_this_month' => 10]);
    }
}
