<div>
    <x-list label="Usuários vinculados">
        @foreach ($users as $user)
            <x-list.items :name="$user->name" wire:key="group-user-{{ $user->id }}">
                @if ($user->pivot->is_coordinator)
                    <x-slot:action>
                        <x-badge text="Coord." color="primary" xs light />
                    </x-slot:action>
                @endif

                @if ($canManageUsers)
                    <x-slot:menu>
                        <x-dropdown.items text="Desvincular" icon="user-minus" wire:click="removeUser({{ $user->id }})" wire:confirm="Desvincular este usuário do grupo?" />
                    </x-slot:menu>
                @endif
            </x-list.items>
        @endforeach
        @if ($users->count() > 0 && $canManageUsers)
            <div class="p-2 border-t border-gray-200 dark:border-dark-700 text-center">
                <x-button text="Vincular usuário" wire:click="$toggle('modal')" sm flat />
            </div>
        @endif

        <x-slot:empty>
            <div class="flex flex-col items-center justify-center space-y-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum usuário vinculado a este grupo.</p>
                @if ($canManageUsers)
                    <x-button text="Vincular usuário" wire:click="$toggle('modal')" sm />
                @endif
            </div>
        </x-slot:empty>        
    </x-list>


    @if ($canManageUsers)
        <x-modal title="Vincular usuário" wire>
            <form id="group-user-add" wire:submit="addUser" class="space-y-4">
                <x-select.styled label="Usuário *" wire:model="userId" :request="route('groups.members.search', $group)" select="label:label|value:value" required />
                <x-checkbox label="É coordenador(a) deste grupo" wire:model="isLeader" />
            </form>

            <x-slot:footer>
                <x-button submit form="group-user-add" text="Vincular" loading="addUser" round />
            </x-slot:footer>
        </x-modal>
    @endif
</div>
