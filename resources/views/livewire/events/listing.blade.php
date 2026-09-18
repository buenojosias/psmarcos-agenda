<x-table :headers="[
    ['index' => 'name', 'label' => 'Evento', 'sortable' => false],
    ['index' => 'group', 'label' => 'Grupo organizador', 'sortable' => false],
    ['index' => 'starts_at', 'label' => 'Início', 'sortable' => false],
    ['index' => 'ends_at', 'label' => 'Término', 'sortable' => false],
    ['index' => 'location', 'label' => 'Local principal', 'sortable' => false],
    ['index' => 'status', 'label' => 'Status', 'sortable' => false],
]" :rows="$events" paginate loading empty="Nenhum evento encontrado.">
    @interact('column_name', $row)
        <div class="font-medium">{{ $row->name }}</div>
        <div class="text-xs text-gray-500 dark:text-dark-400">{{ $row->type->label() }}</div>
    @endinteract

    @interact('column_group', $row)
        {{ $row->group?->name ?? 'Sem grupo organizador' }}
    @endinteract

    @interact('column_starts_at', $row)
        {{ $row->starts_at->format('d/m/Y H:i') }}
    @endinteract

    @interact('column_ends_at', $row)
        {{ $row->ends_at->format('d/m/Y H:i') }}
    @endinteract

    @interact('column_location', $row)
        {{ $row->primaryReservation?->place?->name ?? 'Local não informado' }}
    @endinteract

    @interact('column_status', $row, $memberOnly, $userGroupIds)
        @if (! $memberOnly || in_array($row->group_id, $userGroupIds, true))
            <x-badge :text="$row->status->label()" :color="$row->status->color()" light />
        @endif
    @endinteract
</x-table>
