<div>
    @if (Gate::allows('review', $event))
        <div class="flex flex-wrap gap-2">
            <x-button wire:click="approve" text="Aprovar" color="green" loading="approve" />
            <x-button wire:click="openRejectModal" text="Recusar" color="red" />
        </div>

        <x-modal wire="showRejectModal" title="Recusar evento" center persistent size="md">
            <form id="reject-event-form" wire:submit="reject">
                <x-textarea wire:model="rejectionReason" label="Motivo da recusa" maxlength="2000" required count />
            </form>

            <x-slot:footer>
                <x-button text="Voltar" x-on:click="$wire.showRejectModal = false" flat />
                <x-button submit form="reject-event-form" text="Confirmar recusa" color="red" loading="reject" />
            </x-slot:footer>
        </x-modal>
    @endif
</div>
