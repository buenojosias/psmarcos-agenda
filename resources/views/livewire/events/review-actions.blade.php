<div>
    @if (Gate::allows('review', $event))
        <div class="flex flex-wrap gap-2">
            <x-button wire:click="approve" text="Aprovar" color="green" loading="approve" />
            <x-button wire:click="openRefuseModal" text="Recusar" color="red" />
        </div>

        <x-modal wire="showRefuseModal" title="Recusar evento" center persistent size="md">
            <form id="refuse-event-form" wire:submit="refuse">
                <x-textarea wire:model="refusalReason" label="Motivo da recusa" maxlength="2000" required count />
            </form>

            <x-slot:footer>
                <x-button text="Voltar" x-on:click="$wire.showRefuseModal = false" flat />
                <x-button submit form="refuse-event-form" text="Confirmar recusa" color="red" loading="refuse" />
            </x-slot:footer>
        </x-modal>
    @endif
</div>
