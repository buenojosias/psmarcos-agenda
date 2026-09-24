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
            <x-date wire:model.live="date" label="Nova data" format="DD/MM/YYYY" typeable />
            <x-time wire:model.live="starts_at" label="Horário de início" format="24" />
            <x-time wire:model.live="ends_at" label="Horário de encerramento" format="24" />

            @unless ($event->is_external)
                <div class="md:col-span-3">
                    <x-select.native wire:model.live="community_id" label="Comunidade">
                        <option value="">Selecione uma comunidade</option>
                        @foreach ($communities as $community)
                            <option wire:key="reschedule-community-{{ $community->id }}" value="{{ $community->id }}">
                                {{ $community->name }}
                            </option>
                        @endforeach
                    </x-select.native>
                </div>
            @endunless
        </div>

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
    @elseif ($community_id !== null && $community_id !== $original_community_id)
        <x-card header="Ambientes da nova comunidade">
            <div class="space-y-4">
                <x-alert
                    title="As reservas atuais não serão transferidas automaticamente"
                    text="Escolha os ambientes da nova comunidade. As reservas atuais permanecerão intactas até a confirmação final."
                    color="yellow"
                    icon="exclamation-triangle"
                    light
                />

                <div class="grid gap-4 md:grid-cols-2">
                    <x-select.styled
                        wire:model.live="selected_place_ids"
                        label="Novos ambientes"
                        placeholder="Selecione um ou mais ambientes"
                        :options="$placeOptions"
                        select="label:label|value:value"
                        multiple
                        searchable
                    />

                    <x-select.native
                        wire:model.live="primary_place_id"
                        label="Ambiente principal"
                        :disabled="$primaryPlaceOptions->isEmpty()"
                    >
                        <option value="">Selecione um ambiente</option>
                        @foreach ($primaryPlaceOptions as $placeOption)
                            <option wire:key="reschedule-primary-place-{{ $placeOption['value'] }}" value="{{ $placeOption['value'] }}">
                                {{ $placeOption['label'] }}
                            </option>
                        @endforeach
                    </x-select.native>
                </div>
            </div>
        </x-card>
    @endif

    <div class="flex flex-wrap justify-end gap-3">
        <x-button
            wire:click="preview"
            text="Verificar remarcação"
            icon="magnifying-glass"
            loading="preview"
            outline
        />
    </div>

    @error('reschedule')
        <x-alert :text="$message" color="red" icon="exclamation-circle" light />
    @enderror

    <x-slide :title="$conflicts !== [] ? 'Conflitos de ambientes' : 'Resumo da remarcação'" size="lg" wire="previewSlide" persistent>
        @if ($conflicts !== [])
            <div class="space-y-5">
                <p class="text-sm text-gray-600 dark:text-dark-300">
                    Os ambientes abaixo já estão reservados no intervalo proposto. Ajuste cada conflito e verifique novamente antes de confirmar.
                    As outras reservas da proposta serão mantidas.
                </p>

                @error('reservations')
                    <p class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300" role="alert">
                        {{ $message }}
                    </p>
                @enderror

                @foreach ($conflicts as $requestedConflict)
                    @php($reservationIndex = $requestedConflict['reservation_index'])

                    <section
                        wire:key="reschedule-conflict-{{ $requestedConflict['requested_place']['id'] }}"
                        class="space-y-4 rounded-lg border border-red-200 p-4 dark:border-red-900/70"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-gray-900 dark:text-white">
                                    {{ $requestedConflict['requested_place']['name'] }}
                                </h3>
                                @if ($requestedConflict['is_primary'])
                                    <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">
                                        Principal
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-dark-300">
                                Novo intervalo proposto:
                                {{ \Carbon\CarbonImmutable::parse($requestedConflict['requested_reserved_from'])->format('d/m/Y H:i') }}
                                — {{ \Carbon\CarbonImmutable::parse($requestedConflict['requested_reserved_to'])->format('d/m/Y H:i') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            @foreach ($requestedConflict['conflicts'] as $conflictIndex => $conflict)
                                <div
                                    wire:key="reschedule-occupied-{{ $requestedConflict['requested_place']['id'] }}-{{ $conflictIndex }}"
                                    class="rounded-md bg-red-50 p-3 text-sm dark:bg-red-950/30"
                                >
                                    <p class="font-medium text-red-800 dark:text-red-200">
                                        Ambiente em conflito: {{ $conflict['reserved_place']['name'] }}
                                    </p>
                                    <p class="text-red-700 dark:text-red-300">
                                        Ocupado de {{ \Carbon\CarbonImmutable::parse($conflict['reserved_from'])->format('d/m/Y H:i') }}
                                        a {{ \Carbon\CarbonImmutable::parse($conflict['reserved_to'])->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>

                        @if ($requestedConflict['is_primary'])
                            <x-select.native
                                wire:model.live="reservations.{{ $reservationIndex }}.place_id"
                                label="Selecionar outro ambiente da comunidade"
                            >
                                @foreach ($places as $place)
                                    <option
                                        wire:key="reschedule-primary-alternative-{{ $place->id }}"
                                        value="{{ $place->id }}"
                                    >
                                        {{ $place->name }}
                                    </option>
                                @endforeach
                            </x-select.native>
                        @endif

                        <div class="flex flex-wrap gap-2">
                            @unless ($requestedConflict['is_primary'])
                                <x-button
                                    wire:click="removeReservationFromProposal({{ $reservationIndex }})"
                                    text="Remover desta remarcação"
                                    color="red"
                                    outline
                                    sm
                                    loading="removeReservationFromProposal"
                                />
                            @endunless

                            <x-button
                                wire:click="adjustReservation({{ $reservationIndex }})"
                                text="Ajustar intervalo"
                                color="gray"
                                outline
                                sm
                            />
                        </div>

                        @if ($adjusting_reservations[$reservationIndex] ?? false)
                            <div class="grid gap-4 rounded-lg bg-gray-50 p-4 dark:bg-dark-700 md:grid-cols-2">
                                <x-input
                                    wire:model.live="reservations.{{ $reservationIndex }}.reserved_from"
                                    type="datetime-local"
                                    label="Novo início da reserva"
                                />
                                <x-input
                                    wire:model.live="reservations.{{ $reservationIndex }}.reserved_to"
                                    type="datetime-local"
                                    label="Novo fim da reserva"
                                />
                            </div>
                        @endif

                        @error("reservations.{$reservationIndex}.place_id")
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        @error("reservations.{$reservationIndex}.reserved_to")
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </section>
                @endforeach
            </div>
        @elseif ($previewReady)
            <div class="space-y-5">
                <p class="text-sm text-gray-600 dark:text-dark-300">
                    Confira os dados abaixo. A configuração atual só será substituída após a confirmação.
                </p>
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
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-dark-300">
                            {{ $community_id === $original_community_id ? 'Ambientes que serão mantidos' : 'Novos ambientes' }}
                        </p>

                        <div class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-dark-600 dark:border-dark-600">
                            @foreach ($reservations as $index => $reservation)
                                @php($place = $places->firstWhere('id', (int) $reservation['place_id']))

                                <div
                                    wire:key="reschedule-summary-{{ $index }}"
                                    class="flex flex-col gap-1 p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                >
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $place?->name }}
                                        @if ($reservation['is_primary'])
                                            <span class="text-xs text-blue-700 dark:text-blue-300">(principal)</span>
                                        @endif
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-dark-300">
                                        {{ \Carbon\CarbonImmutable::parse($reservation['reserved_from'])->format('d/m/Y H:i') }}
                                        — {{ \Carbon\CarbonImmutable::parse($reservation['reserved_to'])->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:footer>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-button wire:click="$set('previewSlide', false)" text="Voltar e ajustar" color="gray" outline />
                @if ($conflicts !== [])
                    <x-button wire:click="preview" text="Verificar novamente" icon="arrow-path" loading="preview" />
                @elseif ($canConfirm)
                    <x-button wire:click="openConfirmation" text="Confirmar remarcação" icon="check" loading="openConfirmation" />
                @endif
            </div>
        </x-slot:footer>
    </x-slide>

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
