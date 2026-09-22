<div class="space-y-6">
    <div class="header">
        <div>
            <h1>Editar evento</h1>
            <a href="{{ route('events.show', $event) }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar ao evento</a>
        </div>
    </div>

    <x-tab wire:model.live="tab">
        <x-tab.items tab="general" title="Informações">
            @if ($tab === 'general')
                <livewire:events.edit.general :$event />
            @endif
        </x-tab.items>

        <x-tab.items tab="details" title="Detalhes">
            @if ($tab === 'details')
                <livewire:events.edit.details :$event />
            @endif
        </x-tab.items>

        @if (! $event->is_external)
            <x-tab.items tab="reservations" title="Reservas">
                @if ($tab === 'reservations')
                    <livewire:events.edit.reservations :$event />
                @endif
            </x-tab.items>
        @endif

        <x-tab.items tab="reschedule" title="Remarcar">
            @if ($tab === 'reschedule')
                <livewire:events.edit.reschedule :$event />
            @endif
        </x-tab.items>
    </x-tab>
</div>
