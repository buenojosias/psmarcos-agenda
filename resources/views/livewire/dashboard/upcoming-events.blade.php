<section aria-label="Próximos eventos">
    <x-card header="Próximos eventos" bordered>
        <ul class="divide-y divide-gray-100 dark:divide-dark-600">
            @forelse ($events as $event)
                <li class="flex min-w-0 gap-4 py-4 first:pt-0 last:pb-0">
                    <time datetime="{{ $event->starts_at->toIso8601String() }}" class="flex w-14 shrink-0 flex-col items-center text-primary-700 dark:text-primary-300">
                        <span class="text-2xl font-semibold">{{ $event->starts_at->format('d') }}</span>
                        <span class="text-xs font-semibold uppercase">{{ $event->starts_at->locale('pt_BR')->translatedFormat('M') }}</span>
                        <span class="mt-1 text-xs text-gray-500 dark:text-dark-400">{{ $event->starts_at->format('Y') }}</span>
                    </time>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <a href="{{ route('events.show', $event) }}" class="break-words font-semibold text-gray-800 hover:text-primary-600 hover:underline dark:text-gray-100 dark:hover:text-primary-300">{{ $event->name }}</a>
                        <p class="text-sm text-gray-600 dark:text-dark-300">{{ $event->starts_at->format('H:i') }} – {{ $event->ends_at->format('H:i') }}@if (!$event->starts_at->isSameDay($event->ends_at)) (até {{ $event->ends_at->format('d/m/Y') }})@endif</p>
                        @if ($event->group)
                            <p class="break-words text-sm text-gray-500 dark:text-dark-400">{{ $event->group->name }}</p>
                        @endif
                        @if ($event->community)
                            <p class="break-words text-sm text-gray-500 dark:text-dark-400">{{ $event->community->name }}</p>
                        @endif
                        @if ($event->is_external)
                            @if ($event->detail?->external_location_name)
                                <p class="break-words text-sm text-gray-500 dark:text-dark-400">{{ $event->detail->external_location_name }}</p>
                            @endif
                        @else
                            @php($place = $event->primaryReservation?->place)
                            @if ($place)
                                <p class="break-words text-sm text-gray-500 dark:text-dark-400">{{ $place->main ? $place->main->name.': ' : '' }}{{ $place->name }}</p>
                            @endif
                        @endif
                        @if ($memberOnly && $event->belongs_to_user_group)
                            <div class="mt-1"><x-badge text="Seu grupo" color="primary" light /></div>
                        @endif
                    </div>
                </li>
            @empty
                <li class="text-sm text-gray-500 dark:text-dark-400">Nenhum evento próximo.</li>
            @endforelse
        </ul>
        <x-slot:footer>
            <x-button text="Ver agenda completa" icon="tabler.arrow-right" position="right" :href="route('events.index')" sm flat />
        </x-slot:footer>
    </x-card>
</section>
