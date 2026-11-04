<div class="space-y-6">
    <div class="header">
        <h1>Cadastrar evento</h1>
    </div>

    <x-radio.group wire:model.live="registration_type" card :columns="4" :options="[
        ['label' => 'Data única', 'value' => 'single', 'description' => 'Evento realizado em uma única data.'],
        ['label' => 'Múltiplas datas', 'value' => 'multiple', 'description' => 'Evento realizado em várias datas escolhidas manualmente.'],
        ['label' => 'Recorrente', 'value' => 'recurring', 'description' => 'Evento repetido semanal, quinzenal ou mensalmente.'],
        ['label' => 'Evento externo', 'value' => 'external', 'description' => 'Evento realizado fora dos ambientes da paróquia.'],
    ]" />

    @if ($registration_type === 'single')
        <livewire:events.forms.single wire:key="event-registration-single" />
    @elseif ($registration_type === 'multiple')
        <livewire:events.forms.multiple wire:key="event-registration-multiple" />
    @elseif ($registration_type === 'recurring')
        <livewire:events.forms.recurring wire:key="event-registration-recurring" />
    @else
        <livewire:events.forms.external wire:key="event-registration-external" />
    @endif
</div>
