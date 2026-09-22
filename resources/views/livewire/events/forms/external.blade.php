<form wire:submit="validateDraft" class="space-y-6">
    <x-card header="Dados do evento" shadowless bordered>
        <div class="grid gap-4 md:grid-cols-2">
            <x-input wire:model="name" label="Nome do evento *" maxlength="255" required class="md:col-span-2" />

            <x-input wire:model="complement" label="Complemento" maxlength="255" class="md:col-span-2" />

            <x-select.native wire:model="type" label="Tipo *" required>
                <option value="">Selecione</option>
                @foreach ($types as $eventType)
                    <option wire:key="external-event-type-{{ $eventType->value }}" value="{{ $eventType->value }}">{{ $eventType->label() }}</option>
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

    <x-card header="Local externo" shadowless bordered>
        <div class="grid gap-4 md:grid-cols-2">
            <x-input wire:model="external_location_name" label="Nome do local *" maxlength="255" required />
            <x-input wire:model="external_location_url" type="url" label="Link do local" maxlength="255" />
            <x-textarea wire:model="external_location_address" label="Endereço do local *" required class="md:col-span-2" />
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

    <div class="flex justify-end">
        <x-button submit text="Validar" loading="validateDraft" />
    </div>
</form>
