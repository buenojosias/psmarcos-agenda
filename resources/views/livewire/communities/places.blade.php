<div class="space-y-2">
    @forelse ($places as $place)
        <div wire:key="place-{{ $place->id }}">
            <x-card shadowless bordered x-data="{ expanded: false }">
                <div class="flex justify-between gap-x-4">
                    <span>{{  $place->name }}</span>
                    <div class="flex items-center gap-x-2">
                        @if ($place->subplaces->isNotEmpty())
                            <x-button @click="expanded = !expanded" icon="rectangle-group" flat />
                        @endif
                        <x-dropdown icon="ellipsis-horizontal-circle" flat>
                            <x-dropdown.items text="Adicionar subespaço" />
                            <x-dropdown.items text="Editar" />
                            <x-dropdown.items text="Excluir" separator />
                        </x-dropdown>
                    </div>
                </div>
                @if ($place->subplaces->isNotEmpty())
                    <div class="mt-4 space-y-2" x-show="expanded" x-collapse>
                        <x-label label="Sub espaços" />
                        @foreach ($place->subplaces as $subplace)
                            <div wire:key="subplace-{{ $subplace->id }}">
                                <x-card shadowless bordered>
                                    {{ $subplace->name }}
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
</div>
