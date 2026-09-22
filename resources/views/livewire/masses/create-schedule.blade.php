<div>
    <x-button text="Cadastrar missa recorrente" wire:click="$toggle('modal')" />

    <x-modal id="mass-schedule-modal" title="Cadastrar missa recorrente" size="lg" persistent wire>
        @if ($pending !== null)
            <div class="space-y-4" role="alert">
                <p class="font-semibold">Existem conflitos de reserva de espaço. Deseja cadastrar mesmo assim?</p>
                <p>As missas serão cadastradas em todas as datas, inclusive nas datas com conflito:</p>
                <ul class="space-y-2">
                    @foreach ($conflicts as $conflict)
                        <li wire:key="conflict-{{ $conflict['date'] }}">
                            <strong>{{ \Carbon\CarbonImmutable::parse($conflict['date'])->format('d/m/Y') }}</strong>:
                            {{ implode(', ', $conflict['places']) }}
                        </li>
                    @endforeach
                </ul>
                @foreach ($errors->all() as $message)
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @endforeach
            </div>
        @else
            <form id="mass-schedule-create" wire:key="mass-schedule-form-{{ $formKey }}" wire:submit="save" class="space-y-4">
                <x-select.native wire:model="community_id" label="Comunidade *" required>
                    <option value="">Selecione</option>
                    @foreach ($communities as $community)
                        <option wire:key="community-option-{{ $community->id }}" value="{{ $community->id }}">{{ $community->name }}</option>
                    @endforeach
                </x-select.native>
                <x-input wire:model="motivation" label="Motivação" maxlength="255" />
                <x-select.native wire:model="weekday" label="Dia da semana *" required>
                    <option value="">Selecione</option>
                    @foreach (['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'] as $value => $label)
                        <option wire:key="weekday-option-{{ $value }}" value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-select.native>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-time wire:model="starts_at" label="Horário inicial *" format="24" required />
                    <x-number wire:model="duration_minutes" label="Duração em minutos *" :min="15" :max="150" :step="15" required />
                </div>
                <x-date id="mass-schedule-date-range" wire:model="dateRange" label="Intervalo de datas *" range format="DD/MM/YYYY" min-date="{{ today()->toDateString() }}" hint="Serão cadastradas missas em datas correspondentes ao intervalo selecionado." required />
            </form>
        @endif

        <x-slot:footer>
            @if ($pending !== null)
                <x-button text="Cancelar" color="gray" wire:click="cancel" />
                <x-button text="Cadastrar mesmo assim" color="amber" wire:click="confirm" loading="confirm" />
            @else
                <x-button text="Cancelar" color="gray" wire:click="cancel" />
                <x-button submit form="mass-schedule-create" text="Salvar" loading="save" />
            @endif
        </x-slot:footer>
    </x-modal>
</div>
