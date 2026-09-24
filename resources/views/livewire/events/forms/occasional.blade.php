<form wire:submit="validateDraft" class="space-y-6">
    <x-card header="Dados do evento" shadowless bordered>
        <div class="grid gap-4 md:grid-cols-2">
            <x-input wire:model="name" label="Nome do evento *" maxlength="255" required class="md:col-span-2" />

            <x-input wire:model="complement" label="Complemento" maxlength="255" class="md:col-span-2" />

            <x-select.native wire:model="type" label="Tipo *" required>
                <option value="">Selecione</option>
                @foreach ($types as $eventType)
                    <option wire:key="event-type-{{ $eventType->value }}" value="{{ $eventType->value }}">{{ $eventType->label() }}</option>
                @endforeach
            </x-select.native>

            <x-select.styled wire:model="group_id" label="Grupo organizador *" :options="$groups"
                             select="label:label|value:value" required :searchable="$isMemberOnly" />

            <x-input type="datetime-local" wire:model="starts_at" label="Início *" required />
            <x-input type="datetime-local" wire:model="ends_at" label="Encerramento *" required />
            <x-toggle wire:model="is_public" label="Evento público" />
            <x-toggle wire:model.live="advertisable" label="Solicitar divulgação" />
            @if ($canConfirmImmediately)
                <x-toggle wire:model="confirm_immediately" label="Confirmar imediatamente" />
            @endif
        </div>
    </x-card>

    @if ($advertisable)
        <x-card header="Detalhes do evento" shadowless bordered>
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Não é obrigatório preencher estes campos neste momento, mas será necessário preenchê-los posteriormente para a Pascom divulgar o evento.</p>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-editor wire:model="description" label="Descrição" hint="Informe uma descrição para a divulgação do evento."
                              :toolbar="['style', 'bold', 'italic', 'unordered-list', 'ordered-list', 'link']" />
                </div>
            <x-input wire:model="target_audience" label="Público-alvo" maxlength="255" />
            <x-textarea wire:model="participation_instructions" label="Orientações para participação" />
            <x-toggle wire:model.live="registration_required" label="Inscrição necessária" />
            @if ($registration_required)
                <x-input wire:model="registration_url" type="url" label="Link de inscrição" maxlength="255" />
                <x-date wire:model="registration_deadline" label="Prazo de inscrição" format="DD/MM/YYYY" />
            @endif
            <x-input wire:model="participation_cost" label="Valor / contribuição" maxlength="255" />
            <x-input wire:model="contact_name" label="Nome do contato" maxlength="255" />
            <x-input wire:model="contact_phone" label="Telefone do contato" maxlength="20" />
            </div>
        </x-card>
    @endif

    <x-card header="Local e ambientes" shadowless bordered>
        <div class="grid gap-4 md:grid-cols-2">
            <x-select.native wire:model.live="community_id" label="Comunidade *" required>
                <option value="">Selecione uma comunidade</option>
                @foreach ($communities as $community)
                    <option wire:key="event-community-{{ $community['value'] }}" value="{{ $community['value'] }}">{{ $community['label'] }}</option>
                @endforeach
            </x-select.native>

            <div wire:key="event-place-select-{{ $community_id ?: 'none' }}">
                <x-select.styled wire:model.live="place_ids" label="Ambientes" :options="$places"
                                 select="label:label|value:value" multiple searchable :disabled="blank($community_id)" />
            </div>

            @foreach ($selectedPlaces as $place)
                <section wire:key="event-place-{{ $place->id }}" class="grid gap-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700 md:col-span-2 md:grid-cols-3">
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

    <x-slide title="Conflitos de ambientes" size="lg" wire="conflictsSlide" persistent>
        <div class="space-y-5">
            <p class="text-sm text-gray-600 dark:text-dark-300">
                Os ambientes abaixo já possuem reserva no período solicitado. Nenhuma informação do evento ou da missa reservada é exibida.
            </p>

            @error('place_ids')
                <p class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300" role="alert">{{ $message }}</p>
            @enderror

            @foreach ($placeConflicts as $requestedConflict)
                <section wire:key="place-conflict-{{ $requestedConflict['requested_place']['id'] }}" class="space-y-3 rounded-lg border border-gray-200 p-4 dark:border-dark-600">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $conflictPlaceNames[$requestedConflict['requested_place']['id']] ?? $requestedConflict['requested_place']['name'] }}</h3>
                        <p class="text-sm text-gray-500 dark:text-dark-300">
                            Solicitado de {{ \Carbon\CarbonImmutable::parse($requestedConflict['requested_reserved_from'])->format('d/m/Y H:i') }}
                            a {{ \Carbon\CarbonImmutable::parse($requestedConflict['requested_reserved_to'])->format('d/m/Y H:i') }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        @foreach ($requestedConflict['conflicts'] as $conflict)
                            <div wire:key="reservation-conflict-{{ $requestedConflict['requested_place']['id'] }}-{{ $loop->index }}" class="rounded-md bg-gray-50 p-3 text-sm dark:bg-dark-700">
                                <p class="font-medium text-gray-800 dark:text-dark-100">Ambiente reservado: {{ $conflictPlaceNames[$conflict['reserved_place']['id']] ?? $conflict['reserved_place']['name'] }}</p>
                                <p class="text-gray-500 dark:text-dark-300">
                                    De {{ \Carbon\CarbonImmutable::parse($conflict['reserved_from'])->format('d/m/Y H:i') }}
                                    a {{ \Carbon\CarbonImmutable::parse($conflict['reserved_to'])->format('d/m/Y H:i') }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <x-slot:footer>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-button text="Voltar e ajustar" color="gray" outline wire:click="adjustConflicts" />
                <x-button text="Continuar sem os espaços conflitantes" wire:click="continueWithoutConflictingPlaces"
                          loading="continueWithoutConflictingPlaces" />
            </div>
        </x-slot:footer>
    </x-slide>
</form>
