<form wire:submit="validateDraft" class="space-y-6">
    <x-card header="Dados do evento" shadowless bordered>
        <div class="grid gap-4 md:grid-cols-2">
            <x-input wire:model="name" label="Nome do evento *" maxlength="255" required class="md:col-span-2" />

            <x-input wire:model="subtitle" label="Complemento" maxlength="255" class="md:col-span-2" />

            <x-select.native wire:model="type" label="Tipo *" required>
                <option value="">Selecione</option>
                @foreach ($types as $eventType)
                    <option wire:key="event-type-{{ $eventType->value }}" value="{{ $eventType->value }}">{{ $eventType->label() }}</option>
                @endforeach
            </x-select.native>

            <x-select.styled wire:model="group_id" label="Grupo organizador *" :options="$groups"
                             select="label:label|value:value" required :searchable="$isMemberOnly" />

            <x-input type="datetime-local" wire:model="starts_at" label="Início *" required />
            <x-input type="datetime-local" wire:model="ends_at" label="Término *" required />
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

    <x-card header="Ambientes" shadowless bordered>
        <div class="grid gap-4 md:grid-cols-2">
            <x-select.native wire:model.live="community_id" label="Comunidade">
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

    @if ($draftValidated)
        <p class="text-sm text-green-700 dark:text-green-300" role="status">Dados validados. O evento ainda não foi cadastrado.</p>
    @endif

    <div class="flex justify-end">
        <x-button submit text="Validar dados" loading="validateDraft" />
    </div>
</form>
