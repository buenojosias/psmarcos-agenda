<div>
    <x-button text="Criar grupo" wire:click="$toggle('modal')" round />

    <x-modal title="Criar grupo" wire>
        <form id="group-create" wire:submit="save" class="space-y-4">
            <x-input label="Nome *" wire:model="name" required />

            <x-select.styled label="Tipo *" wire:model="type" :options="$types" select="label:label|value:value" required />

            <x-select.styled label="Comunidade" wire:model="communityId" :options="$communities" select="label:label|value:value" />

            <x-textarea label="Descrição" wire:model="description" />

            @if ($canAssignUser)
                <x-select.styled label="Usuário" wire:model="userId" :request="route('groups.users.search')" select="label:label|value:value" />
                <x-checkbox label="É coordenador deste grupo" wire:model="isLeader" />
            @else
                <x-toggle label="Sou coordenador deste grupo" wire:model="isLeader" />
            @endif
        </form>

        <x-slot:footer>
            <x-button submit form="group-create" text="Salvar" loading="save" round />
        </x-slot:footer>
    </x-modal>
</div>
