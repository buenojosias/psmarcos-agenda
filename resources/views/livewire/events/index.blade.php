<div class="space-y-4">
    <div class="header"><h1>Eventos</h1></div>

    <div class="grid gap-4 md:grid-cols-3">
        @include('livewire.events.filters')

        <x-select.native wire:model.live="scope" label="Exibir">
            <option value="all">Todos os eventos</option>
            <option value="mine">Meus grupos</option>
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
    </div>

    @include('livewire.events.listing')
</div>
