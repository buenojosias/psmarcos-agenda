@props(['model', 'label'])

<div x-data="{
    hours: $wire.entangle(@js($model)),
    format(value) {
        const totalMinutes = Math.round(Number(value || 0) * 60);
        const wholeHours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;

        return wholeHours > 0
            ? `${wholeHours}h${minutes > 0 ? String(minutes).padStart(2, '0') : ''}`
            : `${minutes} min`;
    },
    change(steps) {
        this.hours = Math.max(0, Math.round(Number(this.hours || 0) * 4) + steps) / 4;
    },
    update(event) {
        const input = event.target.value.trim().toLowerCase().replace(/\s+/g, '');
        const hours = input.match(/^(\d+)h(?:(\d{1,2})(?:min)?)?$/);
        const minutes = input.match(/^(\d+)min$/);
        const clock = input.match(/^(\d+):([0-5]\d)$/);
        let totalMinutes = null;

        if (hours && Number(hours[2] || 0) < 60) {
            totalMinutes = Number(hours[1]) * 60 + Number(hours[2] || 0);
        } else if (minutes) {
            totalMinutes = Number(minutes[1]);
        } else if (clock) {
            totalMinutes = Number(clock[1]) * 60 + Number(clock[2]);
        }

        if (totalMinutes !== null && totalMinutes % 15 === 0) {
            this.hours = totalMinutes / 60;
        }

        event.target.value = this.format(this.hours);
    }
}">
    <x-input :name="$model" :label="$label" hint="Use 15 min, 1h ou 1h15." x-bind:value="format(hours)"
             x-on:change="update($event)" x-on:keydown.enter.prevent="update($event)" placeholder="0 min" autocomplete="off">
        <x-slot:prefix button>
            <x-button icon="minus" sm x-on:click="change(-1)" x-bind:disabled="Number(hours || 0) <= 0"
                      aria-label="Diminuir {{ $label }} em 15 minutos" />
        </x-slot:prefix>
        <x-slot:suffix button>
            <x-button icon="plus" sm x-on:click="change(1)"
                      aria-label="Aumentar {{ $label }} em 15 minutos" />
        </x-slot:suffix>
    </x-input>
</div>
