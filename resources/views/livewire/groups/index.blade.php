<div class="space-y-6" x-data="{ filtersOpen: false }">
    <div class="header">
        <h1>Grupos</h1>
        @can('create', \App\Models\Group::class)
            <div class="mb-4 md:mb-0 mt-2">
                <livewire:groups.create @created="$refresh" />
            </div>
        @endcan
    </div>

    <div class="md:hidden">
        <x-button text="Filtros" icon="funnel" outline block color="gray" x-on:click="filtersOpen = !filtersOpen"
                  x-bind:aria-expanded="filtersOpen" aria-controls="group-filters" />
    </div>

    <x-card id="group-filters" bordered shadowless class="grid gap-4 md:grid! md:grid-cols-3"
            x-cloak x-show="filtersOpen"
            x-transition:enter="transition duration-300 ease-out motion-reduce:transition-none"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition duration-200 ease-in motion-reduce:transition-none"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2">
        <x-input wire:model.live.debounce.300ms="search" label="Buscar pelo nome" icon="magnifying-glass" clearable />

        <x-select.native wire:model.live="community" label="Comunidade">
            <option value="">Todas as comunidades</option>
            @foreach ($communities as $community)
                <option wire:key="community-{{ $community->id }}" value="{{ $community->id }}">{{ $community->name }}</option>
            @endforeach
        </x-select.native>

        <div class="flex items-end md:pb-2">
            <x-toggle wire:model.live="myGroups" label="Apenas grupos dos quais participo" />
        </div>
    </x-card>

    <x-table :$headers :$sort :rows="$this->rows" paginate loading>
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
            <x-link :href="route('groups.events.index', $row)" text="Eventos" icon="calendar-days" />
        @endinteract
    </x-table>
</div>
