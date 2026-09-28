<section aria-label="Precisa da sua atenção">
    <x-card header="Precisa da sua atenção" bordered>
        <div @class([
            'grid grid-cols-1 items-start gap-4',
            'sm:grid-cols-2' => count($decisions) + (int) $refusedEvents->isNotEmpty() > 1,
        ])>
            @foreach ($decisions as $decision)
                <x-alert :title="$decision['title']" :color="$decision['color']" :icon="$decision['icon']" light>
                    <x-slot:footer>
                        <x-button :text="$decision['action']" :href="$decision['url']" :color="$decision['color']" sm outline />
                    </x-slot:footer>
                </x-alert>
            @endforeach

            @if ($refusedEvents->isNotEmpty())
                <x-alert title="Eventos precisam de ajustes" text="Estes eventos foram recusados. Consulte o motivo e faça os ajustes necessários." icon="tabler.alert-circle" color="red" light>
                    <x-slot:footer>
                        <ul class="flex flex-col gap-3">
                            @foreach ($refusedEvents as $event)
                                <li class="flex min-w-0 flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <span class="min-w-0 break-words font-medium">{{ $event->name }}</span>
                                    <x-button text="Ver evento" :href="route('events.show', $event)" color="red" sm outline class="shrink-0" />
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4"><x-button text="Consultar eventos recusados" :href="route('events.index', ['status' => 'refused', 'period' => 'all'])" color="red" sm flat /></div>
                    </x-slot:footer>
                </x-alert>
            @endif

            @if ($decisions === [] && $refusedEvents->isEmpty())
                <x-alert title="Tudo em dia" text="Você não possui nenhuma pendência no momento." icon="tabler.circle-check" color="green" light />
            @endif
        </div>
    </x-card>
</section>
