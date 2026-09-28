@props(['rows', 'mode', 'generation'])
<div class="availability-scroll relative max-h-[65vh] overflow-auto rounded-lg border border-gray-200 dark:border-dark-600"
     tabindex="0" role="region" aria-label="Mapa de disponibilidade, role para consultar horários e linhas"
     x-data x-on:availability-reset.window="$el.scrollTop = 0"
     wire:loading.class="opacity-60" wire:target="mode,communityId,placeId,date,previousDay,nextDay,today">
    <div class="availability-header sticky top-0 z-30 flex h-12 bg-white text-xs text-gray-600 dark:bg-dark-800 dark:text-gray-300">
        <div class="availability-label sticky left-0 z-40 flex shrink-0 items-center border-r border-b border-gray-200 bg-white px-3 font-semibold dark:border-dark-600 dark:bg-dark-800">
            {{ $mode === 'data' ? 'Espaço' : 'Data' }}
        </div>
        <div class="availability-hours relative shrink-0 border-b border-gray-200 dark:border-dark-600" aria-label="Horários de 06:00 até 00:00">
            @for ($hour = 6; $hour < 24; $hour++)
                <span class="absolute top-4 pl-1" style="left: {{ ($hour - 6) / 18 * 100 }}%">{{ sprintf('%02d:00', $hour) }}</span>
            @endfor
        </div>
    </div>
    @foreach ($rows as $rowIndex => $row)
        <x-availability.row :row="$row" :row-index="$rowIndex" :mode="$mode" :generation="$generation" />
    @endforeach
    @if ($mode === 'espaco')
        <div wire:key="availability-more-{{ $generation }}-{{ count($rows) }}"
             wire:intersect.once.parent.margin.300px="loadMore({{ count($rows) }}, {{ $generation }})"
             class="sticky left-0 flex h-14 w-full items-center justify-center text-sm text-gray-500 dark:text-gray-400">
            <span wire:loading wire:target="loadMore" role="status">Carregando mais datas…</span>
            <x-button text="Carregar próximos 14 dias" flat color="gray" wire:click="loadMore({{ count($rows) }}, {{ $generation }})" wire:loading.remove wire:target="loadMore" />
        </div>
    @endif
</div>
