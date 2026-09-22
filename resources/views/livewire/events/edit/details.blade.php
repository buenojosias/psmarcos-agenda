<form wire:submit="save" class="space-y-6">
    <x-card header="Detalhes do evento" shadowless bordered>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Todos os campos são opcionais.</p>

        <div class="grid gap-4 md:grid-cols-2">
            <x-input wire:model="subtitle" label="Complemento" maxlength="255" class="md:col-span-2" />

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

    <div class="flex justify-end">
        <x-button submit text="Salvar detalhes" loading="save" />
    </div>
</form>
