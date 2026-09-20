<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // Nome de identificação do evento
            $table->string('type', 20); // Tipo do evento, vindo do enum
            $table->ulid('recurrence_code')->nullable()->index(); // Identificador para eventos recorrentes, a fim de editar ou cancelar em massa (ex: Encontros semanais)
            $table->dateTime('starts_at'); // Horário de início efetivo do evento
            $table->dateTime('ends_at'); // Horário de término efetivo do evento
            $table->string('status', 20)->default('pending'); // Status do agendamento, vindo do enum
            $table->boolean('is_external')->default(false); // O evento não acontecerá nas dependências da igreja, mas deve ser registrado assim mesmo (neste caso, nã haverá relacionamento com places)
            $table->boolean('is_public')->default(true); // Se o evento pode aparecer publicamente no calendário e no site
            $table->boolean('advertisable')->default(false); // Se deseja que o evento seja anunciado nos avisos e nas redes sociais pela Pascom (apenas para eventos não regulares)
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
