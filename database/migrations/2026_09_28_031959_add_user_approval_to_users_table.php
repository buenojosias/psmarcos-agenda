<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $legacyAccounts = ! Schema::hasColumn('users', 'approved_at');

        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_active')->default(false);
            });
        }

        if (! Schema::hasIndex('users', 'users_is_active_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('is_active');
            });
        }

        if (! Schema::hasColumn('users', 'approved_by_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if ($legacyAccounts) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('approved_at')->nullable();
            });

            DB::table('users')->update(['is_active' => true]);
        }
    }

    /** Historical access and approval metadata are retained on rollback; use a forward migration. */
    public function down(): void {}
};
