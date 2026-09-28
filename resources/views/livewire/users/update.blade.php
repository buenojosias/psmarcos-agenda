<div>
    @if($page)
        <x-card header="Editar usuário" shadowless bordered>
            <form wire:submit="save" class="space-y-4">
                <x-input label="Nome *" wire:model="name" required />
                <x-input label="E-mail *" type="email" wire:model="email" required />
                <x-password label="Senha" wire:model="password" hint="Preencha somente se deseja alterar a senha." autocomplete="new-password" />
                <x-password label="Confirmar senha" wire:model="password_confirmation" autocomplete="new-password" />
                <x-select.styled label="Comunidades *" wire:model="communities" :options="$this->communityOptions" select="label:label|value:value" multiple searchable required />
                <x-select.styled label="Perfis de acesso *" wire:model="roles" :options="$this->roleOptions" select="label:label|value:value" multiple required />
                <x-button text="Salvar" submit loading="save" round />
            </form>
            <div class="mt-4"><x-button text="Voltar ao cadastro" :href="route('users.show', $user)" flat /></div>
        </x-card>
    @else
        <x-modal title="Editar usuário" wire>
            <form wire:submit="save" class="space-y-4">
                <x-input label="Nome *" wire:model="name" required />
                <x-input label="E-mail *" type="email" wire:model="email" required />
                <x-password label="Senha" wire:model="password" hint="Preencha somente se deseja alterar a senha." autocomplete="new-password" />
                <x-password label="Confirmar senha" wire:model="password_confirmation" autocomplete="new-password" />
                <x-select.styled label="Comunidades *" wire:model="communities" :options="$this->communityOptions" select="label:label|value:value" multiple searchable required />
                <x-select.styled label="Perfis de acesso *" wire:model="roles" :options="$this->roleOptions" select="label:label|value:value" multiple required />
                <x-button text="Salvar" submit loading="save" round />
            </form>
        </x-modal>
    @endif
</div>
