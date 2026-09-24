<?php

declare(strict_types=1);

use App\Models\EventLog;
use App\Support\EventLogFormatter;

it('translates simple fields and formats their values without exposing technical values', function () {
    $log = new EventLog(['changes' => [
        'name'                  => ['old' => 'Encontro', 'new' => 'Retiro'],
        'type'                  => ['old' => 'meeting', 'new' => 'course'],
        'status'                => ['old' => 'pending', 'new' => 'confirmed'],
        'starts_at'             => ['old' => '2026-10-18 19:30:00', 'new' => '2026-10-19 20:00:00'],
        'registration_deadline' => ['old' => null, 'new' => '2026-10-15'],
        'is_public'             => ['old' => false, 'new' => true],
        'registration_required' => ['old' => 0, 'new' => 1],
        'custom_note'           => ['old' => '', 'new' => 'Aviso aos fiéis'],
        'unknown_id'            => ['old' => 42, 'new' => 43],
    ]]);

    $result = (new EventLogFormatter)->formatMany([$log]);

    expect(array_values($result)[0]['fields'])->toBe([
        ['label' => 'Nome', 'old' => 'Encontro', 'new' => 'Retiro'],
        ['label' => 'Tipo', 'old' => 'Reunião', 'new' => 'Curso, treinamento ou formação'],
        ['label' => 'Status', 'old' => 'Pendente', 'new' => 'Confirmado'],
        ['label' => 'Início', 'old' => '18/10/2026 19:30', 'new' => '19/10/2026 20:00'],
        ['label' => 'Prazo de inscrição', 'old' => 'Não informado', 'new' => '15/10/2026'],
        ['label' => 'Evento público', 'old' => 'Não', 'new' => 'Sim'],
        ['label' => 'Inscrição necessária', 'old' => 'Não', 'new' => 'Sim'],
        ['label' => 'Custom Note', 'old' => 'Não informado', 'new' => 'Aviso aos fiéis'],
        ['label' => 'Unknown', 'old' => 'Não informado', 'new' => 'Não informado'],
    ]);
});

it('keeps unknown structured values and invalid dates out of the formatted output', function () {
    $log = new EventLog(['changes' => [
        'ends_at'    => ['old' => 'invalid-date', 'new' => null],
        'extra_data' => ['old' => ['secret' => 42], 'new' => ['secret' => 43]],
    ]]);

    $result = (new EventLogFormatter)->formatMany([$log]);

    expect(array_values($result)[0]['fields'])->toBe([
        ['label' => 'Encerramento', 'old' => 'Não informado', 'new' => 'Não informado'],
        ['label' => 'Extra Data', 'old' => 'Não informado', 'new' => 'Não informado'],
    ]);
});
