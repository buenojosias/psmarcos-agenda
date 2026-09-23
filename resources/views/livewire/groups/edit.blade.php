<div>
    <x-button text="Editar grupo" wire:click="open" />

    <x-modal title="Editar grupo" size="lg" wire>
        <form id="group-edit" wire:submit="save" class="space-y-4">
            <x-input label="Nome *" wire:model="name" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Abreviação" wire:model="abbreviation" maxlength="30" />
                <x-select.styled label="Tipo *" wire:model="type" :options="$types" select="label:label|value:value" required />
            </div>

            <x-select.styled label="Comunidade" wire:model="communityId" :options="$communities" select="label:label|value:value" />

            <x-textarea label="Descrição" wire:model="description" />

        </form>

        <x-slot:footer>
            <x-button submit form="group-edit" text="Salvar" loading="save" />
        </x-slot:footer>
    </x-modal>
</div>
