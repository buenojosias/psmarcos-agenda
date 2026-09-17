<div>
    <x-modal title="Editar espaço" size="md" wire>
        <form id="place-edit-{{ $community->id }}" wire:submit="save" class="space-y-4">
            <x-input label="Nome do espaço *" wire:model="name" maxlength="255" required />
        </form>

        <x-slot:footer>
            <x-button text="Cancelar" wire:click="$set('modal', false)" flat />
            <x-button submit form="place-edit-{{ $community->id }}" text="Salvar" loading="save" />
        </x-slot:footer>
    </x-modal>
</div>
