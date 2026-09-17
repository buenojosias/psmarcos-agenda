<div class="space-y-2">
    @forelse ($places as $place)
        <div wire:key="place-{{ $place->id }}">
            <x-card shadowless bordered paddingless x-data="{ expanded: false }">
                <div class="px-3 py-2 flex items-center justify-between gap-x-4">
                    <span>{{ $place->name }}</span>
                    <div class="flex items-center gap-x-2">
                        @if ($place->subplaces->isNotEmpty())
                            <x-button @click="expanded = !expanded" icon="chevron-down" flat />
                        @endif
                        @can('create', \App\Models\Place::class)
                            <x-dropdown icon="ellipsis-vertical" flat>
                                <x-dropdown.items text="Adicionar subespaço"
                                    wire:click="$dispatchTo('places.create', 'create-place', { mainPlaceId: {{ $place->id }} })" />
                                @can('update', $place)
                                    <x-dropdown.items text="Editar"
                                        wire:click="$dispatchTo('places.edit', 'edit-place', { placeId: {{ $place->id }} })" />
                                @endcan
                                <x-dropdown.items text="Excluir" separator />
                            </x-dropdown>
                        @endcan
                    </div>
                </div>
                @if ($place->subplaces->isNotEmpty())
                    <div class="m-4 space-y-2" x-show="expanded" x-collapse>
                        <x-label label="Sub espaços" />
                        @foreach ($place->subplaces as $subplace)
                            <div wire:key="subplace-{{ $subplace->id }}">
                                <x-card shadowless bordered paddingless>
                                    <div class="px-4 py-2 flex items-center justify-between gap-x-4">
                                        <span>{{ $subplace->name }}</span>
                                        @can('create', \App\Models\Place::class)
                                            <x-dropdown icon="ellipsis-vertical" flat>
                                                @can('update', $subplace)
                                                    <x-dropdown.items text="Editar"
                                                        wire:click="$dispatchTo('places.edit', 'edit-place', { placeId: {{ $subplace->id }} })" />
                                                @endcan
                                                <x-dropdown.items text="Excluir" separator />
                                            </x-dropdown>
                                        @endcan
                                    </div>
                                </x-card>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-dark-400">Nenhum espaço está cadastrado para esta comunidade.</p>
    @endforelse
    @can('create', \App\Models\Place::class)
        <x-button text="Adicionar espaço" wire:click="$dispatchTo('places.create', 'create-place')" />
        <livewire:places.create :community="$community" @created="$refresh" />
    @endcan
</div>
