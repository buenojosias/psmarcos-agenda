<div class="space-y-2">
    @forelse ($places as $place)
        <div wire:key="place-{{ $place->id }}">
            @if ($place->subplaces->isNotEmpty())
                <x-card :header="$place->name" minimize="mount" shadowless bordered>
                    <div class="space-y-2">
                        @foreach ($place->subplaces as $subplace)
                            <div wire:key="subplace-{{ $subplace->id }}">
                                <x-card shadowless bordered>
                                    {{ $subplace->name }}
                                </x-card>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @else
                <x-card shadowless bordered>
                    {{ $place->name }}
                </x-card>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-dark-400">Nenhum espaço está cadastrado para esta comunidade.</p>
    @endforelse
</div>
