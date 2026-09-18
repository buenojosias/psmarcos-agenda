<x-input wire:model.live.debounce.300ms="search" label="Buscar pelo nome" icon="magnifying-glass" maxlength="100" />

<x-select.native wire:model.live="period" label="Período">
    <option value="upcoming">Próximos e em andamento</option>
    <option value="past">Passados</option>
    <option value="all">Todos os períodos</option>
</x-select.native>

<x-select.native wire:model.live="status" label="Status">
    <option value="">Todos os status</option>
    @foreach (\App\Enums\EventStatusEnum::cases() as $eventStatus)
        <option wire:key="status-{{ $eventStatus->value }}" value="{{ $eventStatus->value }}">{{ $eventStatus->label() }}</option>
    @endforeach
</x-select.native>
