<div>
    <x-button text="Cadastrar grupo" wire:click="$toggle('modal')" />

    <x-modal title="Cadastrar grupo" size="lg" wire>
        <form id="group-create" wire:submit="save" class="space-y-4">
            <x-input label="Nome *" wire:model="name" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Abreviação" wire:model="abbreviation" maxlength="30" />
                <x-select.styled label="Tipo *" wire:model="type" :options="$types" select="label:label|value:value" required />
            </div>

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
            <x-button submit form="group-create" text="Salvar" loading="save" />
        </x-slot:footer>
    </x-modal>
</div>
