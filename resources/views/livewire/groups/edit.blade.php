<div>
    <x-button text="Editar grupo" wire:click="open" />

    <x-modal title="Editar grupo" wire>
        <form id="group-edit" wire:submit="save" class="space-y-4">
            <x-input label="Nome *" wire:model="name" required />

            <x-select.styled label="Tipo *" wire:model="type" :options="$types" select="label:label|value:value" required />

            <x-select.styled label="Comunidade" wire:model="communityId" :options="$communities" select="label:label|value:value" />

            <x-textarea label="Descrição" wire:model="description" />

        </form>

        <x-slot:footer>
            <x-button submit form="group-edit" text="Salvar" loading="save" />
        </x-slot:footer>
    </x-modal>
</div>
