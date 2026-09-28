<div>
    <x-card header="Usuários" shadowless bordered>
        <div class="mb-4"><livewire:users.create @created="$refresh" /></div>
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-input label="Buscar nome ou e-mail" wire:model.live.debounce.300ms="search" icon="tabler.search" />
            <x-select.styled label="Status" wire:model.live="status" :options="[['label' => 'Todos', 'value' => ''], ['label' => 'Aguardando aprovação', 'value' => 'pending'], ['label' => 'Ativos', 'value' => 'active'], ['label' => 'Inativos', 'value' => 'inactive']]" select="label:label|value:value" required />
            <x-select.styled label="Comunidade" wire:model.live="community" :options="$this->communityOptions" select="label:label|value:value" />
            <x-select.styled label="Perfil" wire:model.live="role" :options="$this->roleOptions" select="label:label|value:value" />
        </div>
        <div class="space-y-3 md:hidden">
            @forelse($this->rows as $row)
                <div wire:key="user-mobile-{{ $row->id }}" class="rounded-lg border border-gray-200 p-4 dark:border-dark-600">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ $row->name }}</h2>
                        <x-badge :text="$row->statusLabel()" :color="$row->is_active ? 'green' : ($row->isPending() ? 'yellow' : 'red')" light />
                    </div>
                    <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        <p class="break-all">{{ $row->email }}</p>
                        <p><strong>Comunidades:</strong> {{ $row->communities->pluck('name')->join(', ') ?: 'Nenhuma' }}</p>
                        <p><strong>Perfis:</strong> {{ collect($row->getRolesArray())->map(fn ($role) => \App\Enums\UserRoleEnum::tryFrom($role)?->label() ?? $role)->join(', ') }}</p>
                        <p><strong>Cadastro:</strong> {{ $row->created_at->format('d/m/Y') }}</p>
                    </div>
                    <div class="mt-3"><x-button :text="$row->isPending() ? 'Revisar cadastro' : 'Ver cadastro'" :href="route('users.show', $row)" sm flat /></div>
                </div>
            @empty
                <p class="text-sm text-gray-600 dark:text-gray-300">Nenhum usuário encontrado para estes filtros.</p>
            @endforelse
            {{ $this->rows->links() }}
        </div>
        <div class="hidden overflow-x-auto md:block">
            <x-table :$headers :$sort :rows="$this->rows" paginate loading :quantity="[2, 5, 15, 25]">
                @interact('column_communities', $row)
                    <span class="text-sm">{{ $row->communities->pluck('name')->join(', ') ?: 'Nenhuma' }}</span>
                @endinteract
                @interact('column_roles', $row)
                    <span class="text-sm">{{ collect($row->getRolesArray())->map(fn ($role) => \App\Enums\UserRoleEnum::tryFrom($role)?->label() ?? $role)->join(', ') }}</span>
                @endinteract
                @interact('column_status', $row)
                    <x-badge :text="$row->statusLabel()" :color="$row->is_active ? 'green' : ($row->isPending() ? 'yellow' : 'red')" light />
                @endinteract
                @interact('column_created_at', $row)
                    {{ $row->created_at->format('d/m/Y') }}
                @endinteract
                @interact('column_action', $row)
                    <x-button :text="$row->isPending() ? 'Revisar cadastro' : 'Ver cadastro'" :href="route('users.show', $row)" sm flat />
                @endinteract
            </x-table>
        </div>
    </x-card>
</div>
