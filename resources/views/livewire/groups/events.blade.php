<div class="space-y-4">
    <div class="header">
        <div>
            <h1>Eventos de {{ $group->name }}</h1>
            <a href="{{ route('groups.show', $group) }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar ao grupo</a>
        </div>
    </div>

    <x-table :headers="[
        ['index' => 'name', 'label' => 'Evento', 'sortable' => false],
        ['index' => 'starts_at', 'label' => 'Início', 'sortable' => false],
        ['index' => 'ends_at', 'label' => 'Término', 'sortable' => false],
        ['index' => 'location', 'label' => 'Local', 'sortable' => false],
        ...($canViewStatus ? [
            ['index' => 'created_at', 'label' => 'Cadastrado em', 'sortable' => false],
            ['index' => 'status', 'label' => '', 'sortable' => false],
        ] : []),
    ]" :rows="$events" paginate loading empty="Nenhum evento cadastrado para este grupo.">
        @interact('column_name', $row)
            <div class="space-y-1">
                <div class="font-medium">{{ $row->name }}</div>
                <div class="text-xs text-gray-500 dark:text-dark-400">{{ $row->type->label() }}</div>
            </div>
        @endinteract

        @interact('column_starts_at', $row)
            {{ $row->starts_at->format('d/m/Y H:i') }}
        @endinteract

        @interact('column_ends_at', $row)
            {{ $row->ends_at->format('d/m/Y H:i') }}
        @endinteract

        @interact('column_location', $row)
            {{ $row->is_external ? ($row->detail?->external_location_name ?? 'Local externo') : ($row->primaryReservation?->place?->name ?? 'Local não informado') }}
            @if (! $row->is_external && $row->primaryReservation?->place?->community)
                <small class="block text-gray-500 dark:text-dark-400">{{ $row->primaryReservation->place->community->name }}</small>
            @endif
        @endinteract

        @if ($canViewStatus)
                @interact('column_created_at', $row)
                    {{ $row->created_at->format('d/m/Y H:i') }}
                @endinteract

            @interact('column_status', $row)
                <x-badge :text="$row->status->label()" :color="$row->status->color()" light />
            @endinteract
        @endif
    </x-table>
</div>
