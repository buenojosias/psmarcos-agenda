<div>
    <x-card shadowless bordered header="Usuários vinculados">
        <x-list>
            @foreach ($users as $user)
                <x-list.items :name="$user->name" wire:key="group-user-{{ $user->id }}">
                    @if ($user->pivot->is_coordinator)
                        <x-badge text="Líder" color="primary" light />
                    @endif

                    @if ($canManageUsers)
                        <x-slot:menu>
                            <x-dropdown.items text="Desvincular" icon="user-minus" wire:click="removeUser({{ $user->id }})" wire:confirm="Desvincular este usuário do grupo?" />
                        </x-slot:menu>
                    @endif
                </x-list.items>
            @endforeach

            <x-slot:empty>
                <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum usuário vinculado a este grupo.</p>
            </x-slot:empty>
        </x-list>

        @if ($canManageUsers)
            <x-slot:footer>
                <x-button text="Vincular usuário" wire:click="$toggle('modal')" round />
            </x-slot:footer>
        @endif
    </x-card>

    @if ($canManageUsers)
        <x-modal title="Vincular usuário" wire>
            <form id="group-user-add" wire:submit="addUser" class="space-y-4">
                <x-select.styled label="Usuário *" wire:model="userId" :request="route('groups.members.search', $group)" select="label:label|value:value" required />
                <x-checkbox label="É líder deste grupo" wire:model="isLeader" />
            </form>

            <x-slot:footer>
                <x-button submit form="group-user-add" text="Vincular" loading="addUser" round />
            </x-slot:footer>
        </x-modal>
    @endif
</div>
