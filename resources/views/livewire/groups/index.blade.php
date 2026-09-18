<div>
    <div class="header">
        <h1>Grupos</h1>
        @can('create', \App\Models\Group::class)
            <div class="mb-4 md:mb-0 mt-2">
                <livewire:groups.create @created="$refresh" />
            </div>
        @endcan
    </div>

    <x-table :$headers :$sort :rows="$this->rows" filter paginate loading :quantity="[10, 25, 50]">
        @interact('column_type', $row)
            {{ $row->type->label() }}
        @endinteract

        @interact('column_community', $row)
            {{ $row->community?->name ?? '—' }}
        @endinteract

        @interact('column_name', $row)
            <a href="{{ route('groups.show', $row) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $row->name }}</a>
        @endinteract

        @interact('column_actions', $row)
            <x-link x-tooltip="Eventos" :href="route('groups.events.index', $row)" icon="calendar-days" />
        @endinteract
    </x-table>
</div>
