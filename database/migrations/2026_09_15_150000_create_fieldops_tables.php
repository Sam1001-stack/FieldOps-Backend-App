<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'public_id')) {
                    $table->uuid('public_id')->nullable()->unique();
                }
                if (! Schema::hasColumn('users', 'is_super_admin')) {
                    $table->boolean('is_super_admin')->default(false);
                }
                if (! Schema::hasColumn('users', 'current_organization_id')) {
                    $table->unsignedBigInteger('current_organization_id')->nullable();
                }
                if (! Schema::hasColumn('users', 'phone')) {
                    $table->string('phone')->nullable();
                }
                if (! Schema::hasColumn('users', 'avatar_path')) {
                    $table->string('avatar_path')->nullable();
                }
                if (! Schema::hasColumn('users', 'large_type')) {
                    $table->boolean('large_type')->default(false);
                }
            });
        }

        if (! Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->string('name');
                $table->string('plan')->default('trial');
                $table->timestamp('trial_ends_at')->nullable();
                $table->boolean('suspended')->default(false);
                $table->unsignedInteger('jobs_this_month')->default(0);
                $table->unsignedInteger('storage_mb_used')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('organization_settings')) {
            Schema::create('organization_settings', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('legal_name')->nullable();
                $table->string('street')->nullable();
                $table->string('zip')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->default('DE');
                $table->string('tax_number')->nullable();
                $table->string('vat_id')->nullable();
                $table->string('iban')->nullable();
                $table->string('bic')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('invoice_prefix')->default('RE');
                $table->unsignedInteger('next_invoice_number')->default(1);
                $table->string('logo_path')->nullable();
                $table->unsignedInteger('photo_retention_months')->default(24);
                $table->boolean('accountant_can_see_photos')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('organization_user')) {
            Schema::create('organization_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role');
                $table->timestamps();
                $table->unique(['organization_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('invitations')) {
            Schema::create('invitations', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
                $table->string('email');
                $table->string('role');
                $table->string('token')->unique();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('label')->nullable();
                $table->string('street');
                $table->string('zip');
                $table->string('city');
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catalog_items')) {
            Schema::create('catalog_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('kind')->default('material');
                $table->string('name');
                $table->string('unit')->default('Stk');
                $table->unsignedInteger('unit_price_cents');
                $table->unsignedSmallInteger('tax_rate')->default(19);
                $table->boolean('is_hourly_rate')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('service_jobs')) {
            Schema::create('service_jobs', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('site_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('internal_notes')->nullable();
                $table->string('status')->default('draft');
                $table->string('urgency')->default('normal');
                $table->string('gewerk')->default('shk');
                $table->timestamp('scheduled_start')->nullable();
                $table->timestamp('scheduled_end')->nullable();
                $table->timestamp('window_start')->nullable();
                $table->timestamp('window_end')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'status'], 'service_jobs_org_status_index');
            });
        }

        if (! Schema::hasTable('job_assignments')) {
            Schema::create('job_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['job_id', 'user_id'], 'job_assignments_job_user_unique');
            });
        }

        if (! Schema::hasTable('job_status_events')) {
            Schema::create('job_status_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('job_photos')) {
            Schema::create('job_photos', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('kind')->default('other');
                $table->string('path');
                $table->boolean('pending_sync')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('job_checklist_items')) {
            Schema::create('job_checklist_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
                $table->string('label');
                $table->boolean('done')->default(false);
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('job_signatures')) {
            Schema::create('job_signatures', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
                $table->string('signer_name')->nullable();
                $table->string('path');
                $table->timestamp('signed_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('time_entries')) {
            Schema::create('time_entries', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('job_materials')) {
            Schema::create('job_materials', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
                $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity', 10, 2);
                $table->unsignedInteger('unit_price_cents');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('number')->nullable();
                $table->string('status')->default('draft');
                $table->date('service_date')->nullable();
                $table->date('issued_at')->nullable();
                $table->unsignedInteger('subtotal_cents')->default(0);
                $table->unsignedInteger('tax_cents')->default(0);
                $table->unsignedInteger('total_cents')->default(0);
                $table->string('pdf_path')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'status'], 'invoices_org_status_index');
            });
        }

        if (! Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->string('description');
                $table->decimal('quantity', 10, 2);
                $table->unsignedInteger('unit_price_cents');
                $table->unsignedSmallInteger('tax_rate')->default(19);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->string('number');
                $table->unsignedInteger('total_cents');
                $table->text('reason')->nullable();
                $table->timestamps();
            });
        }

        $this->addUsersOrganizationForeignKey();
        $this->addInvoiceNumberUnique();

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'current_organization_id')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropForeign('users_current_organization_id_foreign');
                });
            } catch (Throwable) {
            }

            try {
                DB::table('users')->update(['current_organization_id' => null]);
            } catch (Throwable) {
            }
        }

        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('job_materials');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('job_signatures');
        Schema::dropIfExists('job_checklist_items');
        Schema::dropIfExists('job_photos');
        Schema::dropIfExists('job_status_events');
        Schema::dropIfExists('job_assignments');
        Schema::dropIfExists('service_jobs');
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('organizations');

        // Keep extra users.* columns on rollback. SQLite cannot drop unique/FK
        // columns reliably; up() is idempotent via Schema::hasColumn().

        Schema::enableForeignKeyConstraints();
    }

    private function addUsersOrganizationForeignKey(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'current_organization_id')) {
            return;
        }

        $orgIds = Schema::hasTable('organizations')
            ? DB::table('organizations')->pluck('id')
            : collect();

        if ($orgIds->isEmpty()) {
            DB::table('users')->update(['current_organization_id' => null]);
        } else {
            DB::table('users')
                ->whereNotNull('current_organization_id')
                ->whereNotIn('current_organization_id', $orgIds)
                ->update(['current_organization_id' => null]);
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('current_organization_id', 'users_current_organization_id_foreign')
                    ->references('id')
                    ->on('organizations')
                    ->nullOnDelete();
            });
        } catch (Throwable) {
            // Already present after a partial migrate.
        }
    }

    private function addInvoiceNumberUnique(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS invoices_org_number_unique ON invoices (organization_id, number) WHERE number IS NOT NULL');

            return;
        }

        try {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unique(['organization_id', 'number'], 'invoices_org_number_unique');
            });
        } catch (Throwable) {
            // Unique already exists, or SQLite treats duplicate NULL drafts as allowed.
        }
    }
};
