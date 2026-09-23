<div>
    <div class="header">
        <h1>Comunidades</h1>
        @can('create', \App\Models\Community::class)
            <div class="mb-4 md:mb-0 mt-2">
                <livewire:communities.create @created="$refresh" />
            </div>
        @endcan
    </div>

    <x-table :$headers :$sort :rows="$this->rows" :filter="false" loading>
        <x-slot:empty>
            Ainda não há comunidades cadastradas.
        </x-slot:empty>

        @interact('column_name', $row)
            <a href="{{ route('communities.show', $row) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $row->name }}</a>
        @endinteract

        @interact('column_address', $row)
            {{ $row->address ?? '—' }}
        @endinteract

        @interact('column_action', $row)
            <div class="flex flex-wrap gap-2">
                @can('update', $row)
                    <livewire:communities.edit :community="$row" :key="'community-edit-'.$row->id" @community-updated="$refresh" />
                @endcan
                @can('delete', $row)
                    <x-button icon="trash" flat color="red" wire:click="delete({{ $row->id }})" loading="delete" />
                @endcan
            </div>
        @endinteract
    </x-table>
</div>
