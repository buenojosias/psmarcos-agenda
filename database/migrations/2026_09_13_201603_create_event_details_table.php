<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();

            // Apresentação pública
            $table->string('subtitle')->nullable(); // ex: "Uma noite especial de música e confraternização"
            $table->text('description')->nullable();

            // Informações para participação
            $table->string('target_audience')->nullable(); // ex: "Famílias, jovens e adultos"
            $table->text('participation_instructions')->nullable(); // ex: "Chegar 15 minutos antes, trazer um prato de comida para compartilhar"

            // Inscrição
            $table->boolean('registration_required')->default(false);
            $table->string('registration_url')->nullable();
            $table->date('registration_deadline')->nullable();

            // Valor/contribuição
            $table->string('participation_cost')->nullable();

            // Contato público
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 20)->nullable();

            // Somente quando o evento for externo
            $table->string('external_location_name')->nullable();
            $table->string('external_location_address')->nullable();
            $table->string('external_location_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_details');
    }
};
