<div class="space-y-6">
    @if ($event->status === \App\Enums\EventStatusEnum::REFUSED)
        <x-alert title="Status: Recusado" color="red" icon="x-circle" light>
            <div class="space-y-2 text-sm">
                <p>
                    <span class="font-semibold">Motivo da recusa:</span>
                    {{ $latestRefusalReason ?: 'Nenhum motivo foi registrado.' }}
                </p>

                @if ($event->reservation_hold_until !== null)
                    <p>
                        <span class="font-semibold">Reservas mantidas até:</span>
                        {{ $event->reservation_hold_until->format('d/m/Y H:i') }}
                    </p>
                @endif

                <p>O evento permanece recusado enquanto você realiza os ajustes.</p>
            </div>

            @error('resubmit')
                <p class="mt-3 text-sm font-medium text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror

            <x-slot:footer>
                <x-button
                    wire:click="openResubmitConfirmation"
                    text="Enviar novamente para aprovação"
                    color="red"
                    loading="openResubmitConfirmation"
                />
            </x-slot:footer>
        </x-alert>

        <x-modal
            wire="resubmitConfirmation"
            title="Enviar novamente para aprovação"
            size="md"
            center
            persistent
        >
            <p class="text-sm text-gray-600 dark:text-dark-300">
                As informações e reservas serão validadas novamente. Deseja enviar este evento para uma nova aprovação?
            </p>

            <x-slot:footer>
                <x-button
                    wire:click="$set('resubmitConfirmation', false)"
                    text="Voltar"
                    outline
                />
                <x-button
                    wire:click="resubmit"
                    text="Confirmar reenvio"
                    color="red"
                    loading="resubmit"
                />
            </x-slot:footer>
        </x-modal>
    @endif

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
