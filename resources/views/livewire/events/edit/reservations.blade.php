<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Reservas de ambientes</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Cada alteração é salva imediatamente.</p>
        </div>

        <x-button text="Adicionar reserva" icon="plus" wire:click="openCreate" />
    </div>

    @error('reservation')
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <x-table :$headers :rows="$reservations" :paginate="false" :filter="false" compact>
        @interact('column_place_community_name', $row)
            {{ $row->place->community?->name ?? '—' }}
        @endinteract

        @interact('column_reserved_from', $row)
            <span class="whitespace-nowrap">{{ $row->reserved_from->format('d/m/Y H:i') }}</span>
        @endinteract

        @interact('column_reserved_to', $row)
            <span class="whitespace-nowrap">{{ $row->reserved_to->format('d/m/Y H:i') }}</span>
        @endinteract

        @interact('column_is_primary', $row)
            @if ($row->is_primary)
                <x-badge text="Principal" color="green" light />
            @else
                <span class="text-gray-400">—</span>
            @endif
        @endinteract

        @interact('column_action', $row)
            <div class="flex flex-wrap justify-end gap-2">
                @if (! $row->is_primary)
                    <x-button text="Definir como principal" sm light
                              wire:click="setPrimary({{ $row->id }})"
                              loading="setPrimary({{ $row->id }})" />
                @endif

                <x-button text="Editar" sm outline wire:click="openEdit({{ $row->id }})" />
                <x-button text="Excluir" sm color="red" light
                          wire:click="deleteReservation({{ $row->id }})"
                          loading="deleteReservation({{ $row->id }})" />
            </div>
        @endinteract

        <x-slot:empty>
            Nenhuma reserva cadastrada.
        </x-slot:empty>
    </x-table>

    <x-modal wire="reservationModal"
             :title="$editingReservationId === null ? 'Adicionar reserva' : 'Editar reserva'"
             size="lg"
             center="md">
        <form id="event-reservation-form" wire:submit="saveReservation" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-select.native wire:model="place_id" label="Ambiente *">
                    <option value="">Selecione</option>
                    @foreach ($places as $place)
                        <option wire:key="reservation-place-{{ $place->id }}" value="{{ $place->id }}">
                            {{ $place->name }} — {{ $place->community?->name ?? 'Sem comunidade' }}
                        </option>
                    @endforeach
                </x-select.native>
            </div>

            <x-input wire:model="reserved_from" type="datetime-local" label="Início da reserva *" />
            <x-input wire:model="reserved_to" type="datetime-local" label="Fim da reserva *" />
        </form>

        <x-slot:footer>
            <x-button text="Cancelar" flat wire:click="$set('reservationModal', false)" />
            <x-button submit form="event-reservation-form" text="Salvar reserva" loading="saveReservation" />
        </x-slot:footer>
    </x-modal>
</div>
