<section aria-label="Próximas missas">
    <x-card header="Próximas missas" bordered>
        <ul class="divide-y divide-gray-100 dark:divide-dark-600">
            @forelse ($masses as $mass)
                <li class="flex min-w-0 flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:gap-6">
                    <time datetime="{{ $mass->starts_at->toIso8601String() }}" class="shrink-0 text-sm font-medium text-primary-700 dark:text-primary-300">{{ $mass->starts_at->format('d/m/Y · H:i') }}</time>
                    <div class="min-w-0">
                        <p class="break-words font-semibold text-gray-800 dark:text-gray-100">{{ $mass->community?->name ?? 'Comunidade não informada' }}</p>
                        <p class="break-words text-sm text-gray-500 dark:text-dark-400">{{ $mass->motivation ?? $mass->schedule?->motivation ?? 'Missa' }}</p>
                    </div>
                </li>
            @empty
                <li class="text-sm text-gray-500 dark:text-dark-400">Nenhuma missa próxima.</li>
            @endforelse
        </ul>
        <x-slot:footer><x-button text="Ver todas as missas" :href="route('masses.index')" sm flat /></x-slot:footer>
    </x-card>
</section>
