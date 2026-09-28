<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

it('upgrades a legacy users schema without blocking existing accounts', function () {
    Schema::table('users', function (Blueprint $table): void {
        $table->dropForeign(['approved_by_user_id']);
        $table->dropColumn(['approved_by_user_id', 'approved_at']);
        $table->dropIndex('users_is_active_index');
    });
    $id = DB::table('users')->insertGetId([
        'name'  => 'Legacy user', 'email' => 'legacy@example.com', 'password' => 'hash', 'is_active' => false,
        'roles' => '["admin"]', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $migration = require glob(database_path('migrations/*add_user_approval_to_users_table.php'))[0];

    $migration->up();

    $legacy = User::findOrFail($id);
    expect($legacy->is_active)->toBeTrue();
    expect($legacy->approved_at)->toBeNull();
    expect($legacy->approved_by_user_id)->toBeNull();
    expect(Schema::hasIndex('users', 'users_is_active_index'))->toBeTrue();
    $new = User::create(['name' => 'New', 'email' => 'new@example.com', 'password' => 'password123', 'roles' => ['member']]);
    expect($new->fresh()->is_active)->toBeFalse();
});

it('preserves already migrated inactive accounts when the migration is rerun', function () {
    $pending   = User::factory()->pending()->create();
    $migration = require glob(database_path('migrations/*add_user_approval_to_users_table.php'))[0];

    $migration->up();

    expect($pending->fresh()->isPending())->toBeTrue();
});
