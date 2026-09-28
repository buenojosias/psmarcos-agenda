<div>
    <x-button text="Criar usuário" wire:click="$toggle('modal')" round />
    <x-modal title="Criar usuário" wire>
        <form wire:submit="save" class="space-y-4">
            <x-input label="Nome *" wire:model="name" required />
            <x-input label="E-mail *" type="email" wire:model="email" required />
            <x-password label="Senha" wire:model="password" hint="Defina uma senha de pelo menos 8 caracteres." autocomplete="new-password" />
            <x-password label="Confirmar senha" wire:model="password_confirmation" autocomplete="new-password" />
            <x-select.styled label="Comunidades *" wire:model="communities" :options="$this->communityOptions" select="label:label|value:value" multiple searchable required />
            <x-select.styled label="Perfis de acesso *" wire:model="roles" :options="$this->roleOptions" select="label:label|value:value" multiple required />
            <x-button text="Salvar" submit loading="save" round />
        </form>
    </x-modal>
</div>
