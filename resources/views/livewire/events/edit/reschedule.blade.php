<div class="space-y-6">
    @if ($saved)
        <x-alert
            title="Evento remarcado"
            text="A nova configuração foi salva com sucesso."
            color="green"
            icon="check-circle"
            light
        />
    @endif

    <x-card header="Nova data e horário">
        <div class="grid gap-4 md:grid-cols-3">
            <x-input wire:model.live="date" type="date" label="Data" />
            <x-input wire:model.live="starts_at" type="time" label="Horário inicial" />
            <x-input wire:model.live="ends_at" type="time" label="Horário final" />
        </div>

        @error('ends_at')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </x-card>

    @if ($event->is_external)
        <x-card header="Local externo">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input wire:model.live="external_location_name" label="Nome do local" />
                <x-input wire:model.live="external_location_address" label="Endereço" />
                <div class="md:col-span-2">
                    <x-input
                        wire:model.live="external_location_url"
                        type="url"
                        label="Link do local"
                        hint="Opcional"
                    />
                </div>
            </div>
        </x-card>
    @else
        <x-card header="Reservas propostas">
            <div class="space-y-5">
                <x-select.native wire:model.live="community_id" label="Comunidade">
                    <option value="">Selecione uma comunidade</option>
                    @foreach ($communities as $community)
                        <option wire:key="reschedule-community-{{ $community->id }}" value="{{ $community->id }}">
                            {{ $community->name }}
                        </option>
                    @endforeach
                </x-select.native>

                @if ($community_id !== null && $community_id !== $original_community_id)
                    <x-alert
                        title="Nova comunidade"
                        text="Selecione manualmente as novas reservas. As reservas atuais permanecerão intactas até a confirmação final."
                        color="yellow"
                        icon="exclamation-triangle"
                        light
                    />
                @endif

                <div class="space-y-4">
                    @forelse ($reservations as $index => $reservation)
                        <div
                            wire:key="reschedule-reservation-{{ $index }}"
                            class="rounded-lg border border-gray-200 p-4 dark:border-dark-600"
                        >
                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                                <x-select.native
                                    wire:model.live="reservations.{{ $index }}.place_id"
                                    label="Ambiente"
                                >
                                    <option value="">Selecione um ambiente</option>
                                    @foreach ($places as $place)
                                        <option
                                            wire:key="reschedule-place-{{ $index }}-{{ $place->id }}"
                                            value="{{ $place->id }}"
                                        >
                                            {{ $place->name }}
                                        </option>
                                    @endforeach
                                </x-select.native>

                                <x-input
                                    wire:model.live="reservations.{{ $index }}.reserved_from"
                                    type="datetime-local"
                                    label="Início da reserva"
                                />

                                <x-input
                                    wire:model.live="reservations.{{ $index }}.reserved_to"
                                    type="datetime-local"
                                    label="Fim da reserva"
                                />

                                <div class="flex flex-wrap gap-2">
                                    @if ($reservation['is_primary'])
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">
                                            Principal
                                        </span>
                                    @else
                                        <x-button
                                            wire:click="makePrimary({{ $index }})"
                                            text="Tornar principal"
                                            color="blue"
                                            outline
                                            sm
                                        />
                                    @endif

                                    <x-button
                                        wire:click="removeReservation({{ $index }})"
                                        text="Remover"
                                        color="red"
                                        outline
                                        sm
                                    />
                                </div>
                            </div>

                            @error("reservations.{$index}.place_id")
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @empty
                        <p class="text-sm text-gray-600 dark:text-dark-300">
                            Nenhuma reserva foi adicionada à proposta.
                        </p>
                    @endforelse
                </div>

                @error('reservations')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <x-button
                    wire:click="addReservation"
                    text="Adicionar reserva"
                    icon="plus"
                    outline
                    :disabled="$community_id === null"
                />
            </div>
        </x-card>
    @endif

    <div class="flex flex-wrap justify-end gap-3">
        <x-button
            wire:click="preview"
            text="Gerar preview"
            icon="eye"
            loading="preview"
            outline
        />

        @if ($previewReady)
            <x-button
                wire:click="openConfirmation"
                text="Confirmar remarcação"
                icon="check"
                loading="openConfirmation"
                :disabled="! $canConfirm"
            />
        @endif
    </div>

    @error('reschedule')
        <x-alert :text="$message" color="red" icon="exclamation-circle" light />
    @enderror

    @if ($previewReady)
        <x-card header="Preview da remarcação">
            <div class="space-y-5">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-dark-300">Nova data e horário</p>
                    <p class="mt-1 font-medium text-gray-900 dark:text-white">
                        {{ \Carbon\CarbonImmutable::parse("{$date} {$starts_at}")->format('d/m/Y H:i') }}
                        — {{ $ends_at }}
                    </p>
                </div>

                @if ($event->is_external)
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-dark-300">Local</p>
                        <p class="mt-1 text-gray-900 dark:text-white">{{ $external_location_name }}</p>
                        <p class="text-sm text-gray-600 dark:text-dark-300">{{ $external_location_address }}</p>
                        @if ($external_location_url !== '')
                            <p class="text-sm text-gray-600 dark:text-dark-300">{{ $external_location_url }}</p>
                        @endif
                    </div>
                @else
                    <div class="space-y-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-dark-300">Ambientes e intervalos</p>

                        @foreach ($reservations as $index => $reservation)
                            @php($place = $places->firstWhere('id', (int) $reservation['place_id']))
                            @php($reservationConflicts = collect($conflicts)->firstWhere('requested_place.id', (int) $reservation['place_id']))

                            <div
                                wire:key="reschedule-preview-{{ $index }}"
                                class="rounded-lg border p-3 {{ $reservationConflicts ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/20' : 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/20' }}"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $place?->name ?? 'Ambiente não selecionado' }}
                                        @if ($reservation['is_primary'])
                                            <span class="text-xs text-blue-700 dark:text-blue-300">(principal)</span>
                                        @endif
                                    </p>
                                    <span class="text-xs font-medium {{ $reservationConflicts ? 'text-red-700 dark:text-red-300' : 'text-green-700 dark:text-green-300' }}">
                                        {{ $reservationConflicts ? 'Com conflito' : 'Disponível' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-dark-300">
                                    {{ \Carbon\CarbonImmutable::parse($reservation['reserved_from'])->format('d/m/Y H:i') }}
                                    — {{ \Carbon\CarbonImmutable::parse($reservation['reserved_to'])->format('d/m/Y H:i') }}
                                </p>

                                @if ($reservationConflicts)
                                    <ul class="mt-2 space-y-1 text-sm text-red-700 dark:text-red-300">
                                        @foreach ($reservationConflicts['conflicts'] as $conflictIndex => $conflict)
                                            <li wire:key="reschedule-conflict-{{ $index }}-{{ $conflictIndex }}">
                                                {{ $conflict['reserved_place']['name'] }}:
                                                {{ \Carbon\CarbonImmutable::parse($conflict['reserved_from'])->format('d/m/Y H:i') }}
                                                — {{ \Carbon\CarbonImmutable::parse($conflict['reserved_to'])->format('d/m/Y H:i') }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-card>
    @endif

    <x-modal
        wire="confirmationModal"
        title="Confirmar remarcação"
        size="md"
        center
        persistent
    >
        <p class="text-sm text-gray-600 dark:text-dark-300">
            Confirma a substituição da configuração atual por esta proposta? Os conflitos serão verificados novamente antes de salvar.
        </p>

        <x-slot:footer>
            <x-button
                wire:click="$set('confirmationModal', false)"
                text="Voltar"
                outline
            />
            <x-button
                wire:click="confirm"
                text="Confirmar"
                color="green"
                loading="confirm"
            />
        </x-slot:footer>
    </x-modal>
</div>
