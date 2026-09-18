<div class="space-y-4">
    <div class="header">
        <div>
            <h1>{{ $community->name }}</h1>
            <a href="{{ route('communities.index') }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar às comunidades</a>
        </div>
        @can('update', $community)
            <livewire:communities.edit :community="$community" @community-updated="refreshCommunity" />
        @endcan
    </div>

    <x-tab wire:model.live="tab" shadowless bordered scroll-on-mobile>
        <x-tab.items tab="information" title="Informações">
            <dl class="grid gap-4 sm:grid-cols-2">
                <x-detail label="Nome oficial" :value="$community->name" />
                <x-detail label="Nome curto" :value="$community->alias" />
                <x-detail label="Sigla" :value="$community->abbreviation" />
                @if ($community->address)
                    <x-detail label="Endereço" :value="$community->address" />
                @endif
            </dl>
        </x-tab.items>

        <x-tab.items tab="groups" title="Grupos">
            @if ($tab === 'groups')
                <x-table :headers="[
                    ['index' => 'name', 'label' => 'Nome', 'sortable' => false],
                    ['index' => 'type', 'label' => 'Tipo', 'sortable' => false],
                    ['index' => 'actions', 'label' => '', 'sortable' => false],
                ]" :rows="$groups" empty="Nenhum grupo está vinculado a esta comunidade.">
                    @interact('column_name', $row)
                        <a href="{{ route('groups.show', $row) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $row->name }}</a>
                    @endinteract
                    @interact('column_type', $row)
                        {{ $row->type->label() }}
                    @endinteract
                    @interact('column_actions', $row)
                        <x-link :href="route('groups.events.index', $row)" x-tooltip="Eventos" icon="calendar-days" />
                    @endinteract
                </x-table>
            @endif
        </x-tab.items>

        <x-tab.items tab="spaces" title="Espaços">
            @if ($tab === 'spaces')
                <livewire:communities.places :community="$community" :key="'community-places-'.$community->id" />
            @endif
        </x-tab.items>

        <x-tab.items tab="calendar" title="Calendário">
            <p class="text-sm text-gray-500 dark:text-dark-400">O calendário da comunidade será disponibilizado em breve.</p>
        </x-tab.items>
    </x-tab>

    @can('create', \App\Models\Place::class)
        <livewire:places.edit :community="$community" />
    @endcan
</div>
