<section aria-label="Pascom" class="flex min-w-0 flex-col gap-4">
    <h2 class="text-lg font-semibold text-primary-950 dark:text-gray-100">Pascom</h2>
    <div class="grid min-w-0 grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card header="Eventos para divulgação" bordered>
            <ul class="divide-y divide-gray-100 dark:divide-dark-600">
                @forelse ($events as $event)
                    <li class="flex min-w-0 flex-col gap-1 py-3 first:pt-0 last:pb-0">
                        <time datetime="{{ $event->starts_at->toIso8601String() }}" class="text-sm font-medium text-primary-700 dark:text-primary-300">{{ $event->starts_at->format('d/m/Y · H:i') }}</time>
                        <a href="{{ route('events.show', $event) }}" class="break-words font-semibold text-gray-800 hover:underline dark:text-gray-100">{{ $event->name }}</a>
                        @if ($event->group)<p class="break-words text-sm text-gray-500 dark:text-dark-400">{{ $event->group->name }}</p>@endif
                        @if ($event->community)<p class="break-words text-sm text-gray-500 dark:text-dark-400">{{ $event->community->name }}</p>@endif
                    </li>
                @empty
                    <li class="text-sm text-gray-500 dark:text-dark-400">Nenhum evento próximo para divulgação.</li>
                @endforelse
            </ul>
            <x-slot:footer><x-button text="Ver eventos confirmados" :href="route('events.index', ['status' => 'confirmed'])" sm flat /></x-slot:footer>
        </x-card>
        <x-card header="Alterações recentes" bordered>
            <p class="mb-4 text-sm text-gray-500 dark:text-dark-400">Últimos 14 dias</p>
            <ul class="divide-y divide-gray-100 dark:divide-dark-600">
                @forelse ($logs as $log)
                    <li class="flex min-w-0 flex-col gap-1 py-3 first:pt-0 last:pb-0">
                        <a href="{{ route('events.show', $log->event) }}" class="break-words font-semibold text-gray-800 hover:underline dark:text-gray-100">{{ $log->event->name }}</a>
                        <p class="text-sm text-gray-600 dark:text-dark-300">{{ $log->action->label() }}</p>
                        <time datetime="{{ $log->created_at->toIso8601String() }}" class="text-xs text-gray-500 dark:text-dark-400">{{ $log->created_at->format('d/m/Y · H:i') }}</time>
                    </li>
                @empty
                    <li class="text-sm text-gray-500 dark:text-dark-400">Nenhuma alteração recente.</li>
                @endforelse
            </ul>
        </x-card>
    </div>
</section>
