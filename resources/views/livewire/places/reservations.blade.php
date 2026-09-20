<div class="space-y-4">
    <div class="header">
        <div>
            <h1>Reservas de {{ $place->name }}</h1>
            <a href="{{ route('communities.show', ['community' => $place->community_id, 'tab' => 'spaces']) }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar aos espaços</a>
        </div>
    </div>

    <x-table :headers="[
        ['index' => 'starts_at', 'label' => 'Início', 'sortable' => false],
        ['index' => 'ends_at', 'label' => 'Término', 'sortable' => false],
        ['index' => 'event', 'label' => 'Evento', 'sortable' => false],
        ['index' => 'group', 'label' => 'Grupo organizador', 'sortable' => false],
    ]" :rows="$reservations" empty="Nenhuma reserva atual ou futura para este espaço.">
        @interact('column_starts_at', $row)
            {{ $row->reserved_from->format('d/m/Y H:i') }}
        @endinteract

        @interact('column_ends_at', $row)
            {{ $row->reserved_to->format('d/m/Y H:i') }}
        @endinteract

        @interact('column_event', $row)
            <div>
                <div class="font-medium">{{ $row->event?->name ?? ($row->mass?->motivation ?? 'Missa') }}</div>
                <div class="text-xs text-gray-500 dark:text-dark-400">{{ $row->event?->type->label() ?? 'Missa' }}</div>
            </div>
        @endinteract

        @interact('column_group', $row)
            {{ $row->event?->group?->name ?? 'Paróquia' }}
        @endinteract
    </x-table>
</div>
