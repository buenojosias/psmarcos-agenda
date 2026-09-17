<div class="space-y-4">
    <div class="header">
        <div>
            <h1>{{ $community->alias }}</h1>
            <a href="{{ route('communities.index') }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar às comunidades</a>
        </div>
        @can('update', $community)
            <livewire:communities.edit :community="$community" @community-updated="refreshCommunity" />
        @endcan
    </div>

    <x-card shadowless bordered header="Detalhes da comunidade">
        <dl class="grid gap-4 sm:grid-cols-2">
            <x-detail label="Nome oficial" :value="$community->name" />
            <x-detail label="Nome curto" :value="$community->alias" />
            <x-detail label="Sigla" :value="$community->abbreviation" />
            @if ($community->address)
                <x-detail label="Endereço" :value="$community->address" />
            @endif
        </dl>
    </x-card>

    <x-card shadowless bordered header="Grupos da comunidade">
        <x-table :headers="[
            ['index' => 'name', 'label' => 'Nome', 'sortable' => false],
            ['index' => 'type', 'label' => 'Tipo', 'sortable' => false],
        ]" :rows="$groups" empty="Nenhum grupo está vinculado a esta comunidade.">
            @interact('column_name', $row)
                <a href="{{ route('groups.show', $row) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $row->name }}</a>
            @endinteract
            @interact('column_type', $row)
                {{ $row->type->label() }}
            @endinteract
        </x-table>
    </x-card>
</div>
