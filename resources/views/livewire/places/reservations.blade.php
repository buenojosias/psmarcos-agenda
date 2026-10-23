<div class="space-y-4">
    <div class="header">
        <div>
            <h1>Reservas de {{ $place->main ? $place->main->name.': ' : '' }}{{ $place->name }}</h1>
            <a href="{{ route('communities.show', ['community' => $place->community_id, 'tab' => 'ambientes']) }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar aos ambientes</a>
        </div>
    </div>

    <div class="max-w-xs">
        <x-date wire:model.live="date" label="Buscar por data" format="DD/MM/YYYY" />
    </div>

    @php
        $user = auth()->user();
        $canViewOtherGroupEvents = $user?->hasAnyRole([
            \App\Enums\UserRoleEnum::ADMIN->value,
            \App\Enums\UserRoleEnum::PRIEST->value,
            \App\Enums\UserRoleEnum::CPP->value,
            \App\Enums\UserRoleEnum::SECRETARY->value,
        ]) ?? false;
        $userGroupIds = $canViewOtherGroupEvents ? [] : ($user?->groups->modelKeys() ?? []);
        $canViewEventDetails = $reservations->getCollection()->mapWithKeys(fn ($reservation) => [
            $reservation->id => $reservation->event === null
                || $reservation->event->status === \App\Enums\EventStatusEnum::CONFIRMED
                || $canViewOtherGroupEvents
                || in_array($reservation->event->group_id, $userGroupIds, true),
        ]);
    @endphp

    <x-table :headers="[
        ['index' => 'starts_at', 'label' => 'Início', 'sortable' => false],
        ['index' => 'ends_at', 'label' => 'Encerramento', 'sortable' => false],
        ['index' => 'event', 'label' => 'Evento', 'sortable' => false],
        ['index' => 'group', 'label' => 'Grupo organizador', 'sortable' => false],
    ]" :rows="$reservations" paginate empty="Nenhuma reserva atual ou futura para este ambiente.">
        @interact('column_starts_at', $row)
            {{ $row->reserved_from->format('d/m/Y H:i') }}
        @endinteract

        @interact('column_ends_at', $row)
            {{ $row->reserved_to->format('d/m/Y H:i') }}
        @endinteract

        @interact('column_event', $row, $canViewEventDetails)
            <div>
                @if ($row->event && $canViewEventDetails[$row->id])
                    <a href="{{ route('events.show', $row->event) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $row->event->name }}</a>
                    <div class="text-xs text-gray-500 dark:text-dark-400">{{ $row->event?->type->label() ?? 'Missa' }}</div>
                @elseif ($row->event)
                    <div class="text-gray-500 dark:text-dark-400">{{ $row->event?->type->label() ?? 'Missa' }}</div>
                    <div class="text-xs text-gray-500 dark:text-dark-400">Detalhes indisponíveis</div>
                @else
                    <div class="font-medium">{{ $row->mass?->motivation ?? 'Missa' }}</div>
                @endif
            </div>
        @endinteract

        @interact('column_group', $row, $canViewEventDetails)
            @if ($canViewEventDetails[$row->id])
                {{ $row->event?->group?->name ?? 'Paróquia' }}
            @endif
        @endinteract
    </x-table>
</div>
