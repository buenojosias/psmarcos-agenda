<div class="space-y-6">
    @php($details = $this->details)
    <x-card header="Cadastro do usuário" shadowless bordered>
        <dl class="grid gap-4 text-sm text-gray-700 dark:text-gray-200 sm:grid-cols-2">
            <div><dt class="font-semibold">Nome</dt><dd>{{ $details->name }}</dd></div>
            <div><dt class="font-semibold">E-mail</dt><dd class="break-all">{{ $details->email }}</dd></div>
            <div><dt class="font-semibold">Comunidades</dt><dd>{{ $details->communities->pluck('name')->join(', ') ?: 'Nenhuma' }}</dd></div>
            <div><dt class="font-semibold">Perfis</dt><dd>{{ collect($details->getRolesArray())->map(fn ($role) => \App\Enums\UserRoleEnum::tryFrom($role)?->label() ?? $role)->join(', ') }}</dd></div>
            <div><dt class="font-semibold">Status</dt><dd>{{ $details->statusLabel() }}</dd></div>
            <div><dt class="font-semibold">Cadastro</dt><dd>{{ $details->created_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="font-semibold">Aprovado por</dt><dd>{{ $details->approvedBy?->name ?? 'Não registrado' }}</dd></div>
            <div><dt class="font-semibold">Data de aprovação</dt><dd>{{ $details->approved_at?->format('d/m/Y H:i') ?? 'Não registrada' }}</dd></div>
        </dl>
        <div class="mt-6 flex flex-wrap gap-3">
            <x-button text="Voltar à lista" :href="route('users.index')" flat />
            @can('update', $details)
                <x-button text="Editar cadastro" :href="route('users.edit', $details)" round />
            @endcan
            @can('setActive', $details)
                <x-button :text="$details->is_active ? 'Desativar conta' : 'Reativar conta'" :color="$details->is_active ? 'red' : 'green'" wire:click="setActive({{ $details->is_active ? 'false' : 'true' }})" loading="setActive" round />
            @endcan
        </div>
        @error('roles') <p class="mt-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </x-card>
    @can('approve', $details)
        <x-card header="Revisar e aprovar cadastro" shadowless bordered>
            <form wire:submit="approve" class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">Confira as comunidades acima e selecione os perfis de acesso. Membro é suficiente para liberar o acesso básico.</p>
                <x-select.styled label="Perfis de acesso *" wire:model="roles" :options="$this->roleOptions" select="label:label|value:value" multiple required />
                <x-button text="Aprovar cadastro" color="green" submit loading="approve" round />
            </form>
        </x-card>
    @endcan
</div>
