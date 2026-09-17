<div>
    <x-button icon="pencil-square" flat wire:click="open" />

    <x-modal title="Editar comunidade" size="lg" wire>
        <form id="community-edit-{{ $community->id }}" wire:submit="save" class="space-y-4">
            <x-input label="Nome da comunidade *" wire:model="name" maxlength="120" required />
            <x-input label="Nome curto *" wire:model="alias" maxlength="30" required />
            <x-input label="Sigla *" wire:model="abbreviation" maxlength="4" required />
            <x-textarea label="Endereço" wire:model="address" maxlength="255" />
        </form>

        <x-slot:footer>
            <x-button submit form="community-edit-{{ $community->id }}" text="Salvar" loading="save" />
        </x-slot:footer>
    </x-modal>
</div>
