<div>
    <x-modal :title="$mainPlaceId === null ? 'Adicionar espaço' : 'Adicionar subespaço'" size="md" wire>
        <form id="place-create-{{ $community->id }}" wire:submit="save" class="space-y-4">
            <x-input label="Nome do espaço *" wire:model="name" maxlength="255" required />
        </form>

        <x-slot:footer>
            <x-button text="Cancelar" wire:click="$set('modal', false)" flat />
            <x-button submit form="place-create-{{ $community->id }}" text="Salvar" loading="save" />
        </x-slot:footer>
    </x-modal>
</div>
