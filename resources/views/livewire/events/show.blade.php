<div class="space-y-6">
    <div class="header">
        <div>
            <h1>{{ $event->name }}</h1>
            @if ($event->group)
                <a href="{{ route('groups.events.index', $event->group) }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar aos eventos do grupo</a>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canUpdate)
                <x-button text="Editar" :href="route('events.edit', $event)" />
                <x-button text="Remarcar" color="yellow" :href="route('events.edit', ['event' => $event, 'tab' => 'reschedule'])" />
            @endif
            @if ($canManage)
                <x-button text="Cancelar" color="red" disabled />
            @endif
            <livewire:events.review-actions :event="$event" @event-reviewed="$refresh" />
        </div>
    </div>

    <x-card shadowless bordered>
        <x-slot:header>
            <div class="text-md font-medium">Informações do evento</div>
            <x-badge :text="$event->status->label()" :color="$event->status->color()" light />
        </x-slot:header>
        <dl class="grid gap-4 sm:grid-cols-2">
            <x-detail label="Nome" :value="$event->name" />
            <x-detail label="Complemento" :value="$event->detail?->subtitle ?? '—'" />
            <x-detail label="Tipo" :value="$event->type->label()" />
            <x-detail label="Local">
                @if ($event->is_external)
                    {{ $event->detail?->external_location_name ?? 'Não informado' }}
                @else
                    @if ($event->community)
                        {{ $event->community?->name ?? 'Sem comunidade' }}
                    @endif
                    <span class="block text-sm text-gray-500 dark:text-dark-400">{{ $primaryReservation?->place?->name ?? 'Não informado' }}</span>
                @endif
            </x-detail>
            <x-detail label="Grupo organizador" :value="$event->group?->name ?? 'Não informado'" />
            <x-detail label="Início" :value="$event->starts_at->format('d/m/Y - H:i')" />
            <x-detail label="Encerramento" :value="$event->ends_at->format('d/m/Y - H:i')" />
            <x-detail label="Evento público" :value="$event->is_public ? 'Sim' : 'Não'" />
            <x-detail label="Divulgação solicitada" :value="$event->advertisable ? 'Sim' : 'Não'" />
        </dl>
    </x-card>

    @if ($event->detail)
        <x-card header="Participação e detalhes" shadowless bordered>
            <dl class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    'description' => 'Descrição',
                    'target_audience' => 'Público-alvo',
                    'participation_instructions' => 'Orientações para participação',
                    'participation_cost' => 'Valor / contribuição',
                    'contact_name' => 'Contato',
                    'contact_phone' => 'Telefone',
                ] as $field => $label)
                    @if ($event->detail->{$field})
                        <div wire:key="event-detail-{{ $field }}">
                            <x-detail :label="$label">
                                <span class="whitespace-pre-line">{{ $event->detail->{$field} }}</span>
                            </x-detail>
                        </div>
                    @endif
                @endforeach
                @if ($event->is_external)
                    @if ($event->detail->external_location_address)
                        <x-detail label="Endereço" :value="$event->detail->external_location_address" />
                    @endif
                    @if ($event->detail->external_location_url)
                        <x-detail label="Referência do local" :value="$event->detail->external_location_url" />
                    @endif
                @endif
                <x-detail label="Inscrição necessária" :value="$event->detail->registration_required ? 'Sim' : 'Não'" />
                @if ($event->detail->registration_deadline)
                    <x-detail label="Prazo de inscrição" :value="$event->detail->registration_deadline->format('d/m/Y')" />
                @endif
                @if ($event->detail->registration_url)
                    <x-detail label="Endereço para inscrição" :value="$event->detail->registration_url" />
                @endif
            </dl>
        </x-card>
    @endif

    @if (! $event->is_external)
        <x-card header="Reservas de espaços" shadowless bordered>
            <x-table :headers="[
                ['index' => 'place', 'label' => 'Espaço', 'sortable' => false],
                ['index' => 'reserved_from', 'label' => 'Início da reserva', 'sortable' => false],
                ['index' => 'reserved_to', 'label' => 'Fim da reserva', 'sortable' => false],
            ]" :rows="$reservations" highlight empty="Nenhuma reserva de espaço para este evento.">
                @interact('column_place', $row)
                    <p class="font-medium">{{ $row->place?->name ?? 'Espaço não informado' }}</p>
                    <small class="block text-gray-500 dark:text-dark-400">{{ $row->place?->community?->name }}</small>
                @endinteract
                @interact('column_reserved_from', $row)
                    {{ $row->reserved_from->format('d/m/Y H:i') }}
                @endinteract
                @interact('column_reserved_to', $row)
                    {{ $row->reserved_to->format('d/m/Y H:i') }}
                @endinteract
            </x-table>
        </x-card>
    @endif
</div>
