<div class="space-y-6">
    <div class="header">
        <div>
            <h1>{{ $event->name }}</h1>
            @if ($event->group)
                <a href="{{ route('groups.events.index', $event->group) }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">← Voltar aos eventos do grupo</a>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canManage)
                <x-button text="Editar" disabled />
                <x-button text="Remarcar" color="yellow" disabled />
                <x-button text="Cancelar" color="red" disabled />
            @endif
            @if ($canReview)
                <x-button text="Aprovar" color="green" disabled />
                <x-button text="Recusar" color="red" disabled />
            @endif
        </div>
    </div>

    <x-card header="Informações do evento" shadowless bordered>
        <dl class="grid gap-4 sm:grid-cols-2">
            <x-detail label="Tipo" :value="$event->type->label()" />
            <x-detail label="Grupo organizador" :value="$event->group?->name ?? 'Paróquia'" />
            <x-detail label="Início" :value="$event->starts_at->format('d/m/Y H:i')" />
            <x-detail label="Término" :value="$event->ends_at->format('d/m/Y H:i')" />
            <x-detail label="Evento público" :value="$event->is_public ? 'Sim' : 'Não'" />
            <x-detail label="Divulgação solicitada" :value="$event->advertisable ? 'Sim' : 'Não'" />
            <x-detail label="Status">
                <x-badge :text="$event->status->label()" :color="$event->status->color()" light />
            </x-detail>
            @if ($event->is_external)
                <x-detail label="Local externo" :value="$event->detail?->external_location_name ?? 'Não informado'" />
                @if ($event->detail?->external_location_address)
                    <x-detail label="Endereço" :value="$event->detail->external_location_address" />
                @endif
                @if ($event->detail?->external_location_url)
                    <x-detail label="Referência do local" :value="$event->detail->external_location_url" />
                @endif
            @endif
        </dl>
    </x-card>

    @if ($event->detail)
        <x-card header="Participação e detalhes" shadowless bordered>
            <dl class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    'subtitle' => 'Subtítulo',
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
