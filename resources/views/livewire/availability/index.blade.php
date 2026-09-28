<div class="space-y-6">
    <div class="header">
        <div>
            <h1>Mapa de disponibilidade</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Visualize os horários ocupados e disponíveis dos ambientes da paróquia.</p>
        </div>
    </div>

    <x-tab wire:model.live="mode" shadowless bordered scroll-on-mobile>
        @foreach (['data' => 'Por data', 'ambiente' => 'Por ambiente'] as $tabMode => $tabTitle)
            <x-tab.items :tab="$tabMode" :title="$tabTitle">
                @if ($mode === $tabMode)
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="w-full sm:w-64">
                            <x-select.native wire:model.live="communityId" label="Comunidade" :disabled="$this->communities->isEmpty()">
                                @if ($this->communities->isEmpty())
                                    <option value="">Nenhuma comunidade acessível</option>
                                @endif
                                @foreach ($this->communities as $community)
                                    <option value="{{ $community->id }}">{{ $community->name }}</option>
                                @endforeach
                            </x-select.native>
                        </div>
                        @if ($mode === 'ambiente')
                            <div class="w-full sm:w-72">
                                <x-select.native wire:model.live="placeId" label="Ambiente" :disabled="$this->places->isEmpty()">
                                    <option value="">Selecione um ambiente</option>
                                    @foreach ($this->places as $place)
                                        <option value="{{ $place->id }}">{{ $place->name }}</option>
                                    @endforeach
                                </x-select.native>
                            </div>
                        @endif
                        <div class="flex items-end gap-2">
                            @if ($mode === 'data')
                                <x-button.circle icon="chevron-left" color="gray" outline wire:click="previousDay" aria-label="Dia anterior" />
                            @endif
                            <div class="w-44">
                                <x-date wire:model.live="date" :label="$mode === 'data' ? 'Data' : 'Ir para data'" format="DD/MM/YYYY" />
                            </div>
                            @if ($mode === 'data')
                                <x-button.circle icon="chevron-right" color="gray" outline wire:click="nextDay" aria-label="Próximo dia" />
                            @endif
                        </div>
                        <x-button text="Hoje" color="gray" outline wire:click="today" />
                    </div>
                @endif
            </x-tab.items>
        @endforeach
    </x-tab>

    <div class="relative">
        <div wire:loading.delay wire:target="mode,communityId,placeId,date,previousDay,nextDay,today"
             class="pointer-events-none absolute top-2 right-2 z-40 rounded-md bg-white/95 px-3 py-1 text-xs text-gray-500 shadow-sm dark:bg-dark-800/95 dark:text-gray-400"
             role="status" aria-live="polite">
            Atualizando disponibilidade…
        </div>

        @if ($this->communities->isEmpty())
            <p class="py-10 text-center text-gray-500">Nenhuma comunidade acessível.</p>
        @elseif ($this->places->isEmpty())
            <p class="py-10 text-center text-gray-500">Esta comunidade ainda não possui ambientes cadastrados.</p>
        @elseif ($mode === 'ambiente' && $placeId === '')
            <p class="py-10 text-center text-gray-500">Selecione um ambiente válido desta comunidade para consultar a disponibilidade.</p>
        @else
            <x-availability.grid :rows="$rows" :mode="$mode" :generation="$generation" />
        @endif
    </div>

    <div class="flex flex-wrap gap-x-6 gap-y-2 text-xs text-gray-600 dark:text-gray-300" aria-label="Legenda">
        <span class="flex items-center gap-2"><span class="size-3 rounded border border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950"></span>Disponível</span>
        <span class="flex items-center gap-2"><x-icon name="calendar-days" sm />Horário ocupado</span>
        <span class="flex items-center gap-2"><x-icon name="lock-closed" sm />Horário ocupado — detalhes restritos</span>
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400">A régua de 15 minutos é apenas visual. As reservas incluem os períodos de preparação e liberação do ambiente.</p>

    <x-slide wire="showDetail" title="Detalhes do horário" size="md">
        @if ($detail)
            <x-availability.details :detail="$detail" />
        @endif
    </x-slide>
</div>
