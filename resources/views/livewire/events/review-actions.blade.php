<div>
    @can('review', $event)
        <div class="w-full grid sm:grid-cols-2 gap-2">
            <x-button wire:click="approve" text="Aprovar" color="green" loading="approve" block />
            <x-button wire:click="openRefuseModal" text="Não aprovar" color="red" block />
        </div>

        <x-modal wire="showRefuseModal" title="Não aprovar evento" center persistent size="md">
            <form id="refuse-event-form" wire:submit="refuse">
                <x-textarea wire:model="refusalReason" label="Motivo da não aprovação" maxlength="2000" required count />
            </form>

            <x-slot:footer>
                <x-button text="Voltar" x-on:click="$wire.showRefuseModal = false" flat />
                <x-button submit form="refuse-event-form" text="Confirmar não aprovação" color="red" loading="refuse" />
            </x-slot:footer>
        </x-modal>
    @endcan
</div>
