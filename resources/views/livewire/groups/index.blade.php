<div>
    @can('create', \App\Models\Group::class)
        <div class="mb-4">
            <livewire:groups.create @created="$refresh" />
        </div>
    @endcan

    <x-card shadowless bordered header="Grupos">
        <x-table :$headers :$sort :rows="$this->rows" filter paginate loading :quantity="[10, 25, 50]">
            @interact('column_type', $row)
                {{ $row->type->label() }}
            @endinteract

            @interact('column_community', $row)
                {{ $row->community?->name ?? '—' }}
            @endinteract
        </x-table>
    </x-card>
</div>
