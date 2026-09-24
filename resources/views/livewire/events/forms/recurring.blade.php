<form wire:submit="validateDraft" class="space-y-6">
    <x-card header="Dados do evento" shadowless bordered>
        <div class="grid gap-4 md:grid-cols-2">
            <x-input wire:model="name" label="Nome do evento *" maxlength="255" required class="md:col-span-2" />

            <x-input wire:model="complement" label="Complemento" maxlength="255" class="md:col-span-2" />

            <x-select.native wire:model="type" label="Tipo *" required>
                <option value="">Selecione</option>
                @foreach ($types as $eventType)
                    <option wire:key="recurring-event-type-{{ $eventType->value }}" value="{{ $eventType->value }}">{{ $eventType->label() }}</option>
                @endforeach
            </x-select.native>

            <x-select.styled wire:model="group_id" label="Grupo organizador *" :options="$groups"
                             select="label:label|value:value" required :searchable="$isMemberOnly" />

            <x-date wire:model="dates" label="Datas *" multiple format="DD/MM/YYYY" :min-date="today()" required />
            <div class="grid grid-cols-2 gap-4">
                <x-time wire:model="starts_time" label="Horário inicial *" format="24" />
                <x-time wire:model="ends_time" label="Horário final *" format="24" />
            </div>

            <x-toggle wire:model="is_public" label="Evento público" />
            @if ($canConfirmImmediately)
                <x-toggle wire:model="confirm_immediately" label="Confirmar imediatamente" />
            @endif
        </div>
    </x-card>

    <x-card header="Local e ambientes" shadowless bordered>
        <div class="grid gap-4 md:grid-cols-2">
            <x-select.native wire:model.live="community_id" label="Comunidade *" required>
                <option value="">Selecione uma comunidade</option>
                @foreach ($communities as $community)
                    <option wire:key="recurring-event-community-{{ $community['value'] }}" value="{{ $community['value'] }}">{{ $community['label'] }}</option>
                @endforeach
            </x-select.native>

            <div wire:key="recurring-event-place-select-{{ $community_id ?: 'none' }}">
                <x-select.styled wire:model.live="place_ids" label="Ambientes" :options="$places"
                                 select="label:label|value:value" multiple searchable :disabled="blank($community_id)" />
            </div>

            @foreach ($selectedPlaces as $place)
                <section wire:key="recurring-event-place-{{ $place->id }}" class="grid gap-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700 md:col-span-2 md:grid-cols-3">
                    <p class="font-medium md:self-center">{{ $place->main === null ? $place->name : $place->main->name.': '.$place->name }}</p>
                    <x-number wire:model="place_hours.{{ $place->id }}.before" label="Horas antes" :min="0" :step="0.25" />
                    <x-number wire:model="place_hours.{{ $place->id }}.after" label="Horas depois" :min="0" :step="0.25" />
                </section>
            @endforeach
        </div>
    </x-card>

    <div class="flex justify-end">
        <x-button submit text="Validar" loading="validateDraft" />
    </div>

    <x-slide title="Disponibilidade das ocorrências" size="lg" wire="conflictsSlide">
        <div class="space-y-5">
            <p class="text-sm text-gray-600 dark:text-dark-300">
                A disponibilidade é verificada separadamente em cada data. Nenhuma informação do evento ou da missa reservada é exibida.
            </p>

            @foreach ($occurrences as $date => $occurrence)
                <section wire:key="recurring-occurrence-{{ $date }}" class="space-y-3 rounded-lg border border-gray-200 p-4 dark:border-dark-600">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            {{ \Carbon\CarbonImmutable::parse($date)->format('d/m/Y') }}
                        </h3>

                        @if ($occurrence['status'] === 'available')
                            <span class="text-sm font-medium text-green-700 dark:text-green-300">
                                ✓ {{ $occurrence['conflicts'] === [] ? 'Disponível' : 'Disponível com ambientes ajustados' }}
                            </span>
                        @elseif ($occurrence['status'] === 'unavailable')
                            <span class="text-sm font-medium text-red-700 dark:text-red-300">Indisponível</span>
                        @else
                            <span class="text-sm font-medium text-amber-700 dark:text-amber-300">Conflito de ambientes</span>
                        @endif
                    </div>

                    @if ($occurrence['status'] === 'unavailable')
                        <p class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300" role="alert">
                            Todos os ambientes desta data possuem conflito.
                        </p>
                    @elseif ($occurrence['status'] === 'conflict')
                        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-950/40 dark:text-amber-300" role="alert">
                            Remova os ambientes conflitantes para manter esta ocorrência.
                        </p>
                    @endif

                    @foreach ($occurrence['conflicts'] as $requestedConflict)
                        <div wire:key="recurring-conflict-{{ $date }}-{{ $requestedConflict['requested_place']['id'] }}" class="space-y-2 rounded-md bg-gray-50 p-3 text-sm dark:bg-dark-700">
                            <div>
                                <p class="font-medium text-gray-800 dark:text-dark-100">
                                    Ambiente solicitado: {{ $requestedConflict['requested_place']['name'] }}
                                </p>
                                <p class="text-gray-500 dark:text-dark-300">
                                    Intervalo solicitado: {{ \Carbon\CarbonImmutable::parse($requestedConflict['requested_reserved_from'])->format('d/m/Y H:i') }}
                                    a {{ \Carbon\CarbonImmutable::parse($requestedConflict['requested_reserved_to'])->format('d/m/Y H:i') }}
                                </p>
                            </div>

                            @foreach ($requestedConflict['conflicts'] as $conflict)
                                <div wire:key="recurring-reservation-conflict-{{ $date }}-{{ $requestedConflict['requested_place']['id'] }}-{{ $loop->index }}" class="border-t border-gray-200 pt-2 dark:border-dark-600">
                                    <p class="font-medium text-gray-800 dark:text-dark-100">
                                        Ambiente reservado: {{ $conflict['reserved_place']['name'] }}
                                    </p>
                                    <p class="text-gray-500 dark:text-dark-300">
                                        Reserva existente: {{ \Carbon\CarbonImmutable::parse($conflict['reserved_from'])->format('d/m/Y H:i') }}
                                        a {{ \Carbon\CarbonImmutable::parse($conflict['reserved_to'])->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    @if ($occurrence['status'] === 'conflict')
                        <div class="flex justify-end">
                            <x-button text="Remover ambientes conflitantes desta data" wire:click="removeConflictingPlaces('{{ $date }}')"
                                      loading="removeConflictingPlaces('{{ $date }}')" />
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </x-slide>
</form>
