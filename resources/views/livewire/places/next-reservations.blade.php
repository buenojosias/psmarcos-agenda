<div>
    <x-slide title="Próximas reservas" size="md" wire>
        @if ($place)
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-dark-400">{{ $place->name }}</p>
                </div>

                @forelse ($reservations as $reservation)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-dark-600">
                        <div class="font-medium">{{ $reservation['type'] }}</div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-dark-400">
                            <div>Início: {{ $reservation['starts_at'] }}</div>
                            <div>Encerramento: {{ $reservation['ends_at'] }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-dark-400">Nenhuma reserva atual ou futura para este espaço.</p>
                @endforelse
            </div>

            <x-slot:footer>
                <div class="flex w-full justify-end">
                    <a href="{{ route('places.reservations', $place) }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        Ver todas as reservas
                    </a>
                </div>
            </x-slot:footer>
        @endif
    </x-slide>
</div>
