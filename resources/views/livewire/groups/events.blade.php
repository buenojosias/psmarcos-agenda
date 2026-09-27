<div class="space-y-4">
    <div class="header">
        <div>
            <h1>Eventos de {{ $group->abbreviation ?? $group->name }}</h1>
            <a href="{{ route('groups.show', $group) }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar ao grupo</a>
        </div>
    </div>

    <x-table :headers="[
        ['index' => 'name', 'label' => 'Evento', 'sortable' => false],
        ['index' => 'starts_at', 'label' => 'Início', 'sortable' => false],
        ['index' => 'ends_at', 'label' => 'Encerramento', 'sortable' => false],
        ['index' => 'location', 'label' => 'Local', 'sortable' => false],
        ...($canViewStatus ? [
            ['index' => 'created_at', 'label' => 'Cadastrado em', 'sortable' => false],
            ['index' => 'status', 'label' => '', 'sortable' => false],
        ] : []),
    ]" :rows="$events" paginate loading empty="Nenhum evento cadastrado para este grupo.">
        @interact('column_name', $row)
            <div class="space-y-1">
                <a href="{{ route('events.show', $row) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $row->name }}</a>
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
            @if (! $row->is_external)
                @php($place = $row->primaryReservation?->place)
                @if ($place?->community?->name)
                    <div>{{ $place->community->name }}</div>
                @endif
                <div class="text text-gray-500 dark:text-dark-400 flex gap-1">
                    <x-icon name="arrow-turn-down-right" sm />
                    {{ $place?->main ? $place->main->name.': ' : '' }}{{ $place?->name ?? 'Local não informado' }}
                </div>
            @else
                <div>{{ $row->detail?->external_location_name ?? 'Local externo' }}</div>
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
