<div class="space-y-4">
    <div class="header"><h1>Missas</h1></div>

    <div class="grid gap-4 md:grid-cols-2">
        <x-select.native wire:model.live="weekday" label="Dia da semana">
            <option value="">Todos os dias</option>
            <option value="0">Domingo</option>
            <option value="1">Segunda-feira</option>
            <option value="2">Terça-feira</option>
            <option value="3">Quarta-feira</option>
            <option value="4">Quinta-feira</option>
            <option value="5">Sexta-feira</option>
            <option value="6">Sábado</option>
        </x-select.native>

        <x-select.native wire:model.live="motivation" label="Motivação">
            <option value="">Todas as motivações</option>
            @foreach ($motivations as $motivationName)
                <option wire:key="motivation-{{ md5($motivationName) }}" value="{{ $motivationName }}">{{ $motivationName }}</option>
            @endforeach
        </x-select.native>
    </div>

    <x-table :headers="[
        ['index' => 'name', 'label' => 'Motivação', 'sortable' => false],
        ['index' => 'starts_at', 'label' => 'Horário', 'sortable' => false],
        ['index' => 'community', 'label' => 'Comunidade', 'sortable' => false],
    ]" :rows="$events" paginate loading empty="Nenhuma missa encontrada.">
        @interact('column_name', $row)
            <a href="{{ route('events.show', $row) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $row->name }}</a>
        @endinteract

        @interact('column_starts_at', $row)
            {{ $row->starts_at->format('d/m/Y H:i') }}
        @endinteract

        @interact('column_community', $row)
            {{ $row->primaryReservation?->place?->community?->name ?? 'Comunidade não informada' }}
        @endinteract
    </x-table>
</div>
