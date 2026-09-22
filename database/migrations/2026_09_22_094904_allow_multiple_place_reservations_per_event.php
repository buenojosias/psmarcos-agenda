<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('place_reservations', function (Blueprint $table) {
            $table->index(['event_id', 'place_id'], 'place_reservations_event_id_place_id_index');
            $table->dropUnique('place_reservations_event_id_place_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('place_reservations', function (Blueprint $table) {
            $table->unique(['event_id', 'place_id'], 'place_reservations_event_id_place_id_unique');
            $table->dropIndex('place_reservations_event_id_place_id_index');
        });
    }
};
