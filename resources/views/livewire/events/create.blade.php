<div class="space-y-6">
    <div class="header">
        <h1>Cadastrar evento</h1>
    </div>

    <x-radio.group wire:model.live="registration_type" card :columns="2" :options="[
        ['label' => 'Data única', 'value' => 'single', 'description' => 'Evento realizado em uma única data.'],
        ['label' => 'Múltiplas datas', 'value' => 'multiple', 'description' => 'Evento realizado em várias datas escolhidas manualmente.'],
        ['label' => 'Recorrente', 'value' => 'recurring', 'description' => 'Evento repetido semanal, quinzenal ou mensalmente.'],
        ['label' => 'Evento externo', 'value' => 'external', 'description' => 'Evento realizado fora dos ambientes da paróquia.'],
    ]" />

    <div wire:loading wire:target="registration_type" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"
         role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-primary-500 border-t-transparent" aria-hidden="true"></span>
        Carregando formulário…
    </div>

    <div wire:loading.class="opacity-50" wire:target="registration_type" class="transition-opacity duration-150">
        @if ($registration_type === 'single')
            <livewire:events.forms.single wire:key="event-registration-single" />
        @elseif ($registration_type === 'multiple')
            <livewire:events.forms.multiple wire:key="event-registration-multiple" />
        @elseif ($registration_type === 'recurring')
            <livewire:events.forms.recurring wire:key="event-registration-recurring" />
        @elseif ($registration_type === 'external')
            <livewire:events.forms.external wire:key="event-registration-external" />
        @else
            <p class="text-sm text-gray-600 dark:text-gray-300">Selecione o tipo de evento para continuar.</p>
        @endif
    </div>
</div>
