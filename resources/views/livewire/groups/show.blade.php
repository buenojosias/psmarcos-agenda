<div class="space-y-6">
    <a href="{{ route('groups.index') }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar aos grupos</a>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card shadowless bordered :header="$group->name">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <x-group-detail label="Tipo">{{ $group->type->label() }}</x-group-detail>
                    <x-group-detail label="Comunidade">{{ $group->community?->name ?? '—' }}</x-group-detail>
                    @if ($group->description)
                        <x-group-detail label="Descrição" class="sm:col-span-2">
                            <span class="whitespace-pre-line">{{ $group->description }}</span>
                        </x-group-detail>
                    @endif
                </dl>
            </x-card>
        </div>

        <div class="space-y-6 lg:col-span-1">
            <livewire:groups.users :group="$group" />

            <x-card shadowless bordered header="Status dos eventos">
                <x-list>
                    <x-list.items name="Aprovados"><x-badge :text="(string) $group->confirmed_events_count" color="green" light /></x-list.items>
                    <x-list.items name="Rejeitados"><x-badge :text="(string) $group->rejected_events_count" color="red" light /></x-list.items>
                    <x-list.items name="Pendentes"><x-badge :text="(string) $group->pending_events_count" color="yellow" light /></x-list.items>
                    <x-list.items name="Remarcados"><x-badge :text="(string) $group->rescheduled_events_count" color="blue" light /></x-list.items>
                    <x-list.items name="Total"><x-badge :text="(string) $group->events_count" color="primary" light /></x-list.items>
                </x-list>

                <x-slot:footer>
                    <x-link text="Ver eventos" href="#" x-on:click.prevent />
                </x-slot:footer>
            </x-card>
        </div>
    </div>
</div>
