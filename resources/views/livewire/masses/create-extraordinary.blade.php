<div>
    <x-button text="Cadastrar missa extraordinária" wire:click="$toggle('modal')" />

    <x-modal id="mass-extraordinary-modal" title="Cadastrar missa extraordinária" size="lg" persistent wire>
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
            <form id="mass-extraordinary-create" wire:key="mass-extraordinary-form-{{ $formKey }}" wire:submit="save" class="space-y-4">
                <x-select.native wire:model="community_id" label="Comunidade *" required>
                    <option value="">Selecione</option>
                    @foreach ($communities as $community)
                        <option wire:key="community-option-{{ $community->id }}" value="{{ $community->id }}">{{ $community->name }}</option>
                    @endforeach
                </x-select.native>
                <x-input wire:model="motivation" label="Motivação *" maxlength="255" required />
                <x-date wire:model="date" label="Data *" format="DD/MM/YYYY" min-date="{{ today()->toDateString() }}" required />
                <x-time wire:model="starts_at" label="Horário inicial *" format="24" required />
                <x-time wire:model="ends_at" label="Horário final *" format="24" required />
            </form>
        @endif

        <x-slot:footer>
            @if ($pending !== null)
                <x-button text="Cancelar" color="gray" wire:click="cancel" />
                <x-button text="Cadastrar mesmo assim" color="amber" wire:click="confirm" loading="confirm" />
            @else
                <x-button text="Cancelar" color="gray" wire:click="cancel" />
                <x-button submit form="mass-extraordinary-create" text="Salvar" loading="save" />
            @endif
        </x-slot:footer>
    </x-modal>
</div>
