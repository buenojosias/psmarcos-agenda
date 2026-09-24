<div class="space-y-6">
    <div class="header">
        <div>
            <h1>Auditoria do evento</h1>
            <a href="{{ route('events.show', $event) }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar ao evento</a>
        </div>
    </div>

    <div class="space-y-2">
        <h2 class="text-lg font-medium">{{ $event->name }}</h2>
        <p class="text-sm text-gray-500 dark:text-dark-400">Histórico de alterações e decisões realizadas sobre este evento.</p>
    </div>

    @if ($logGroups === [])
        <x-card shadowless bordered>
            <p class="text-sm text-gray-500 dark:text-dark-400">Nenhum registro de auditoria encontrado para este evento.</p>
        </x-card>
    @else
        <ol aria-label="Histórico de auditoria" class="ml-2 space-y-6 border-l border-gray-200 pl-6 dark:border-dark-600">
            @foreach ($logGroups as $group)
                <li wire:key="audit-entry-{{ $loop->index }}" class="relative">
                    <span aria-hidden="true" class="absolute -left-[31px] top-5 size-3 rounded-full bg-primary-600 ring-4 ring-white dark:bg-primary-400 dark:ring-dark-900"></span>
                    <x-card shadowless bordered>
                        <p class="mb-3 text-sm text-gray-500 dark:text-dark-400">
                            {{ $group[0]->created_at->format('d/m/Y') }} às {{ $group[0]->created_at->format('H:i') }}
                            · {{ $group[0]->user?->name ?? 'Usuário não disponível' }}
                        </p>
                        <ul class="space-y-3">
                            @foreach ($group as $log)
                                <li wire:key="audit-action-{{ $loop->parent->index }}-{{ $loop->index }}" class="space-y-1">
                                    <p class="font-medium">{{ $log->action->label() }}</p>
                                    @if ($log->from_status && $log->to_status)
                                        <p class="text-sm text-gray-600 dark:text-dark-300">{{ $log->from_status->label() }} → {{ $log->to_status->label() }}</p>
                                    @elseif ($log->to_status)
                                        <p class="text-sm text-gray-600 dark:text-dark-300">Status: {{ $log->to_status->label() }}</p>
                                    @elseif ($log->from_status)
                                        <p class="text-sm text-gray-600 dark:text-dark-300">Status anterior: {{ $log->from_status->label() }}</p>
                                    @endif
                                    @if ($log->changes)
                                        <div x-data="{ open: false }" class="pt-1">
                                            <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open.toString()"
                                                    class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                Ver alterações
                                            </button>
                                            <div x-cloak x-show="open" class="mt-3 space-y-3 rounded-lg bg-gray-50 p-3 text-sm dark:bg-dark-700">
                                                @foreach ($formattedChanges[$log->id]['fields'] as $field)
                                                    <div class="space-y-1">
                                                        <p class="font-medium">{{ $field['label'] }}</p>
                                                        <p>Antes: {{ $field['old'] }}</p>
                                                        <p>Depois: {{ $field['new'] }}</p>
                                                    </div>
                                                @endforeach
                                                @foreach ($formattedChanges[$log->id]['reservations'] as $reservation)
                                                    <div class="space-y-1">
                                                        <p class="font-medium">{{ $reservation['place'] }} · {{ $reservation['kind'] }}</p>
                                                        @if ($reservation['old'] !== null)
                                                            <p>Antes: {{ $reservation['old'] }}</p>
                                                        @endif
                                                        @if ($reservation['new'] !== null)
                                                            <p>Depois: {{ $reservation['new'] }}</p>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                @if ($formattedChanges[$log->id]['fields'] === [] && $formattedChanges[$log->id]['reservations'] === [])
                                                    <p class="text-gray-500 dark:text-dark-400">Detalhes não disponíveis.</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                </li>
            @endforeach
        </ol>
    @endif
</div>
