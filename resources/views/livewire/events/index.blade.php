<div class="space-y-6" x-data="{ filtersOpen: false }">
    <div class="header">
        <h1>Eventos</h1>
        <div class="mb-4 md:mb-0 mt-2">
            <x-button text="Adicionar evento" href="{{ route('events.create') }}" />
        </div>
    </div>

    <div class="md:hidden">
        <x-button text="Filtros" icon="funnel" outline block color="gray" x-on:click="filtersOpen = !filtersOpen"
                  x-bind:aria-expanded="filtersOpen" aria-controls="event-filters" />
    </div>

    <x-card id="event-filters" bordered shadowless class="grid gap-4 md:grid! md:grid-cols-3"
         x-cloak x-show="filtersOpen"
         x-transition:enter="transition duration-300 ease-out motion-reduce:transition-none"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition duration-200 ease-in motion-reduce:transition-none"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2">
        @include('livewire.events.filters')

        <x-select.native wire:model.live="scope" label="Exibir">
            <option value="all">Todos os eventos</option>
            <option value="mine">De meus grupos</option>
            @if (! $memberOnly)
                <option value="review">Aguardando análise</option>
            @endif
        </x-select.native>

        <x-select.native wire:model.live="type" label="Tipo">
            <option value="">Todos os tipos</option>
            @foreach ($types as $eventType)
                <option wire:key="type-{{ $eventType->value }}" value="{{ $eventType->value }}">{{ $eventType->label() }}</option>
            @endforeach
        </x-select.native>

        <x-select.native wire:model.live="group" label="Grupo organizador">
            <option value="">Todos os grupos</option>
            @foreach ($groups as $eventGroup)
                <option wire:key="group-{{ $eventGroup->id }}" value="{{ $eventGroup->id }}">{{ $eventGroup->name }}</option>
            @endforeach
        </x-select.native>

        <x-select.native wire:model.live="community" label="Comunidade/local">
            <option value="">Todas as comunidades</option>
            @foreach ($communities as $eventCommunity)
                <option wire:key="community-{{ $eventCommunity->id }}" value="{{ $eventCommunity->id }}">{{ $eventCommunity->name }}</option>
            @endforeach
            <option value="none">Sem comunidade</option>
        </x-select.native>

        <div class="flex items-end md:pb-2">
            <x-toggle wire:model.live="externalOnly" label="Apenas eventos externos" />
        </div>
    </x-card>

    @include('livewire.events.listing')
</div>
