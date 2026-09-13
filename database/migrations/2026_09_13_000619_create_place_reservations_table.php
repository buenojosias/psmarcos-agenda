<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->constrained()->restrictOnDelete();
            $table->dateTime('reserved_from');
            $table->dateTime('reserved_to');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['event_id', 'place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_reservations');
    }
};
