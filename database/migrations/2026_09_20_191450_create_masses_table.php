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
        Schema::create('masses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mass_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('motivation')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['community_id', 'starts_at']);
            $table->index(['mass_schedule_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('masses');
    }
};
