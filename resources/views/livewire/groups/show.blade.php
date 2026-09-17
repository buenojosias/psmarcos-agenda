<div class="space-y-4">
    <div class="header">
        <div>
            <h1>{{ $group->name }}</h1>
            <a href="{{ route('groups.index') }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar aos grupos</a>
        </div>
        @can('update', $group)
            <livewire:groups.edit :group="$group" @group-updated="refreshGroup" />
        @endcan
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card shadowless bordered header="Detalhes do grupo" class="space-y-4">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <x-detail label="Tipo" :value="$group->type->label()" />
                    <x-detail label="Comunidade" :value="$group->community?->name ?? '—'" />
                </dl>
                <dl>
                    @if ($group->description)
                        <x-detail label="Descrição" class="sm:col-span-2">
                            <span class="whitespace-pre-line">{{ $group->description }}</span>
                        </x-detail>
                    @endif
                </dl>
            </x-card>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-1 gap-8 sm:gap-6 lg:gap-8">
            <livewire:groups.users :group="$group" />

            <x-list label="Status dos eventos">
                <x-list.items name="Aprovados">
                    <x-slot:action>
                        <x-badge :text="(string) $group->confirmed_events_count" color="green" light />
                    </x-slot:action>
                </x-list.items>
                <x-list.items name="Pendentes">
                    <x-slot:action>
                        <x-badge :text="(string) $group->pending_events_count" color="yellow" light />
                    </x-slot:action>
                </x-list.items>
                <x-list.items name="Não aprovados">
                    <x-slot:action>
                        <x-badge :text="(string) $group->rejected_events_count" color="red" light />
                    </x-slot:action>
                </x-list.items>
                <x-list.items name="Remarcados">
                    <x-slot:action>
                        <x-badge :text="(string) $group->rescheduled_events_count" color="blue" light />
                    </x-slot:action>
                </x-list.items>
                <x-list.items name="Cancelados">
                    <x-slot:action>
                        <x-badge :text="(string) $group->canceled_events_count" color="gray" light />
                    </x-slot:action>
                </x-list.items>
                <x-list.items name="Total">
                    <x-slot:action>
                        <x-badge :text="(string) $group->events_count" color="primary" light />
                    </x-slot:action>
                </x-list.items>
                <div class="p-2 border-t border-gray-200 dark:border-dark-700 text-center">
                    <x-link text="Ver eventos" href="#" x-on:click.prevent />    
                </div>
            </x-list>
        </div>
    </div>
</div>
