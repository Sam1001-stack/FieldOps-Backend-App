<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'verification_status')) {
                $table->string('verification_status')->default('pending');
            }
            if (! Schema::hasColumn('customers', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
            if (! Schema::hasColumn('customers', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('organization_user', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_user', 'approval_status')) {
                $table->string('approval_status')->default('approved');
            }
            if (! Schema::hasColumn('organization_user', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('organization_user', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'verification_status')) {
            DB::table('customers')->where('verification_status', 'pending')->update([
                'verification_status' => 'verified',
                'verified_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'verified_by')) {
                $table->dropConstrainedForeignId('verified_by');
            }
            foreach (['verification_status', 'verified_at'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('organization_user', function (Blueprint $table) {
            if (Schema::hasColumn('organization_user', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
            foreach (['approval_status', 'approved_at'] as $column) {
                if (Schema::hasColumn('organization_user', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
