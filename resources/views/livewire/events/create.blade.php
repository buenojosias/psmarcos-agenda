<div class="space-y-6">
    <div class="header">
        <h1>Cadastrar evento</h1>
    </div>

    <x-radio.group wire:model.live="registration_type" card :columns="3" :options="[
        ['label' => 'Evento esporádico', 'value' => 'occasional', 'description' => 'Evento realizado em uma única data.'],
        ['label' => 'Múltiplas datas', 'value' => 'multiple', 'description' => 'Evento realizado em várias datas escolhidas manualmente.'],
        ['label' => 'Evento externo', 'value' => 'external', 'description' => 'Evento realizado fora dos ambientes da paróquia.'],
    ]" />

    @if ($registration_type === 'occasional')
        <livewire:events.forms.occasional wire:key="event-registration-occasional" />
    @elseif ($registration_type === 'multiple')
        <livewire:events.forms.multiple wire:key="event-registration-multiple" />
    @else
        <livewire:events.forms.external wire:key="event-registration-external" />
    @endif
</div>
