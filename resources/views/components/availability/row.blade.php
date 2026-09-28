@props(['row', 'rowIndex', 'mode', 'generation'])
@php
    $date = \Carbon\CarbonImmutable::parse($row['date']);
    $gridStart = $date->setTime(6, 0);
    $gridEnd = $date->addDay();
@endphp
<div class="availability-row flex min-h-16" wire:key="availability-row-{{ $generation }}-{{ $rowIndex }}">
    <div class="availability-label sticky left-0 z-20 flex shrink-0 items-center gap-1 border-r border-b border-gray-200 bg-white px-2 py-2 text-sm dark:border-dark-600 dark:bg-dark-800 dark:text-gray-200">
        @if ($mode === 'data')
            <div class="min-w-0 flex-1" style="padding-left: {{ min($row['depth'], 5) * 10 }}px">
                <span class="line-clamp-2">@if ($row['depth'] > 0)<span aria-label="Subespaço">↳ </span>@endif{{ $row['name'] }}</span>
            </div>
            <button type="button" class="shrink-0 rounded p-1 focus-visible:outline-2 focus-visible:outline-primary-500"
                    aria-label="Nome completo: {{ $row['name'] }}" x-tooltip="{{ e($row['name']) }}">
                <x-icon name="tabler.info-circle" sm />
            </button>
        @else
            <div>
                <div class="font-medium">{{ $date->format('d/m/Y') }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $date->translatedFormat('l') }}</div>
            </div>
        @endif
        @foreach ($row['periods'] as $periodIndex => $period)
            @if ($period['end'] <= $gridStart->format('Y-m-d H:i:s'))
                <button type="button" wire:click="openPeriod({{ $rowIndex }}, {{ $periodIndex }})" class="rounded p-1 text-rose-700 focus-visible:outline-2 dark:text-rose-300" aria-label="Ocupação antes das 06:00">
                    <x-icon name="tabler.moon" sm />
                </button>
            @endif
        @endforeach
    </div>
    <div class="availability-timeline relative shrink-0 border-b border-gray-200 bg-emerald-50/50 dark:border-dark-600 dark:bg-emerald-950/20 text-gray-700 dark:text-gray-300">
        @foreach ($row['periods'] as $periodIndex => $period)
            @php
                $start = \Carbon\CarbonImmutable::parse($period['start'])->max($gridStart);
                $end = \Carbon\CarbonImmutable::parse($period['end'])->min($gridEnd);
                $left = $gridStart->diffInMinutes($start) / 1080 * 100;
                $width = max(0, $start->diffInMinutes($end, false)) / 1080 * 100;
            @endphp
            @if ($width > 0)
                <button type="button" wire:click="openPeriod({{ $rowIndex }}, {{ $periodIndex }})"
                        wire:key="availability-period-{{ $generation }}-{{ $rowIndex }}-{{ $periodIndex }}"
                        @class(['absolute inset-y-2 flex items-center gap-1 overflow-hidden rounded border px-1 text-left text-xs text-rose-900 focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-rose-100',
                            'border-rose-300 bg-rose-100 dark:border-rose-800 dark:bg-rose-950' => $period['visible'],
                            'availability-restricted border-dashed border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-950' => ! $period['visible']])
                        style="left: {{ $left }}%; width: {{ $width }}%"
                        aria-label="{{ $period['label'] }}, {{ $row['name'] }}, {{ $date->format('d/m/Y') }}, {{ \Carbon\CarbonImmutable::parse($period['start'])->format('H:i') }} – {{ \Carbon\CarbonImmutable::parse($period['end'])->format('H:i') }}">
                    <x-icon :name="$period['visible'] ? 'tabler.calendar' : 'tabler.lock'" class="size-3 shrink-0" />
                    <span class="truncate">{{ $period['visible'] ? $period['label'] : 'Indisponível' }}</span>
                </button>
            @endif
        @endforeach
    </div>
</div>
