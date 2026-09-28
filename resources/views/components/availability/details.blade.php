@props(['detail'])
<div class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
    <h2 class="text-lg font-semibold">{{ $detail['label'] }}</h2>
    <dl class="space-y-3">
        <div><dt class="font-medium">Data</dt><dd>{{ \Carbon\CarbonImmutable::parse($detail['date'])->format('d/m/Y') }}</dd></div>
        <div><dt class="font-medium">Período ocupado</dt><dd>{{ \Carbon\CarbonImmutable::parse($detail['start'])->format('H:i') }} – {{ \Carbon\CarbonImmutable::parse($detail['end'])->format('H:i') }}{{ \Carbon\CarbonImmutable::parse($detail['end'])->format('H:i') === '00:00' ? ' (dia seguinte)' : '' }}</dd></div>
        <div><dt class="font-medium">Ambiente</dt><dd>{{ $detail['place'] }}</dd></div>
        <div><dt class="font-medium">Comunidade</dt><dd>{{ $detail['community'] }}</dd></div>
    </dl>
    @if (! $detail['visible'])
        <p class="flex items-start gap-2"><x-icon name="lock-closed" class="size-4 shrink-0" />Os detalhes deste evento não estão disponíveis para você.</p>
    @else
        @foreach ($detail['details'] as $source)
            <div class="space-y-2 border-t border-gray-200 pt-4 dark:border-dark-600">
                <h3 class="font-semibold">{{ $source['title'] }}</h3>
                <p>{{ $source['type'] === 'mass' ? 'Missa' : 'Evento' }} · {{ \Carbon\CarbonImmutable::parse($source['start'])->format('d/m/Y H:i') }} – {{ \Carbon\CarbonImmutable::parse($source['end'])->format('d/m/Y H:i') }}</p>
                @if ($source['type'] === 'event')
                    @if ($source['group'])<p>Grupo: {{ $source['group'] }}</p>@endif
                    <p>Status: {{ $source['status'] }}</p>
                    <x-button text="Ver evento" :href="route('events.show', $source['event_id'])" sm />
                @endif
            </div>
        @endforeach
    @endif
</div>
