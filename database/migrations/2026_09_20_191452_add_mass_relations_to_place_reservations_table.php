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
        Schema::table('place_reservations', function (Blueprint $table) {
            $table->dropUnique('place_reservations_event_id_place_id_unique');
            $table->dropForeign(['event_id']);
        });

        Schema::table('place_reservations', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->change();
            $table->foreignId('mass_id')->nullable()->after('event_id')->constrained()->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'place_id']);
            $table->unique(['mass_id', 'place_id']);
        });

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            DB::statement(
                'ALTER TABLE place_reservations ADD CONSTRAINT place_reservations_exactly_one_reservable CHECK ((event_id IS NULL) <> (mass_id IS NULL))'
            );
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE place_reservations DROP CHECK place_reservations_exactly_one_reservable');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE place_reservations DROP CONSTRAINT place_reservations_exactly_one_reservable');
        }

        Schema::table('place_reservations', function (Blueprint $table) {
            $table->dropUnique('place_reservations_mass_id_place_id_unique');
            $table->dropUnique('place_reservations_event_id_place_id_unique');
            $table->dropForeign(['mass_id']);
            $table->dropForeign(['event_id']);
            $table->dropColumn('mass_id');
        });

        Schema::table('place_reservations', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable(false)->change();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'place_id']);
        });
    }
};
