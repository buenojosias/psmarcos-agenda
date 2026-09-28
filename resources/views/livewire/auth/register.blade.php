<div>
    <x-card shadowless bordered header="Criar conta">
        <form wire:submit="save" class="space-y-4">
            <x-input label="Nome *" wire:model="name" required autocomplete="name" />
            <x-input label="E-mail *" type="email" wire:model="email" required autocomplete="email" />
            <x-password label="Senha *" wire:model="password" required autocomplete="new-password" />
            <x-password label="Confirmar senha *" wire:model="password_confirmation" required autocomplete="new-password" />
            <x-select.styled label="Comunidades das quais participa *" wire:model="communities" :options="$this->communityOptions" select="label:label|value:value" multiple searchable required />
            <p class="text-sm text-gray-600 dark:text-gray-300">Após o cadastro, um responsável revisará sua conta para liberar o acesso à agenda.</p>
            <x-button text="Cadastrar" submit loading="save" block round />
            <p class="text-center text-sm text-gray-600 dark:text-gray-300">Já tem uma conta? <a class="font-semibold underline" href="{{ route('login') }}">Entrar</a></p>
        </form>
    </x-card>
</div>
