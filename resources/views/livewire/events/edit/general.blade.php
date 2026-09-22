<form wire:submit="save" class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2">
        <x-input wire:model="name" label="Nome do evento *" maxlength="255" required class="md:col-span-2" />

        <x-select.native wire:model="type" label="Tipo *" required>
            <option value="">Selecione</option>
            @foreach ($types as $eventType)
                <option wire:key="event-type-{{ $eventType->value }}" value="{{ $eventType->value }}">{{ $eventType->label() }}</option>
            @endforeach
        </x-select.native>

        <div class="flex flex-col gap-4">
            <x-toggle wire:model="is_public" label="Evento público" />

            @if ($event->recurrence_code === null)
                <x-toggle wire:model="advertisable" label="Solicitar divulgação" />
            @endif
        </div>
    </div>

    <div class="flex justify-end">
        <x-button submit text="Salvar" loading="save" />
    </div>
</form>
