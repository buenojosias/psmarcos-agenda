@props(['model', 'label', 'timeModel', 'direction'])

<div x-data="{
    hours: $wire.entangle(@js($model)),
    time: $wire.entangle(@js($timeModel)),
    direction: @js($direction),
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
    reservationHint() {
        if (typeof this.time !== 'string' || !/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(this.time)) {
            return this.direction === 'before' ? 'Selecione o horário de início.' : 'Selecione o horário de encerramento.';
        }

        const [hour, minute] = this.time.split(':').map(Number);
        const offset = Math.round(Number(this.hours || 0) * 60);
        const totalMinutes = hour * 60 + minute + (this.direction === 'before' ? -offset : offset);
        const dayOffset = Math.floor(totalMinutes / 1440);
        const clockMinutes = ((totalMinutes % 1440) + 1440) % 1440;
        const clock = `${String(Math.floor(clockMinutes / 60)).padStart(2, '0')}:${String(clockMinutes % 60).padStart(2, '0')}`;
        const dayLabel = dayOffset === -1 ? ' (dia anterior)'
            : dayOffset === 1 ? ' (dia seguinte)'
            : dayOffset < -1 ? ` (${Math.abs(dayOffset)} dias antes)`
            : dayOffset > 1 ? ` (${dayOffset} dias depois)` : '';

        return this.direction === 'before'
            ? `Reserva a partir das ${clock}${dayLabel}`
            : `Reserva até ${clock}${dayLabel}`;
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
    <x-input :name="$model" :label="$label" class="text-center" x-bind:value="format(hours)"
             x-on:change="update($event)" x-on:keydown.enter.prevent="update($event)" placeholder="0 min" autocomplete="off">
        <x-slot:prefix button>
            <x-button icon="minus" sm flat color="gray" x-on:click="change(-1)" x-bind:disabled="Number(hours || 0) <= 0"
                      aria-label="Diminuir {{ $label }} em 15 minutos" />
        </x-slot:prefix>
        <x-slot:suffix button>
            <x-button icon="plus" sm flat color="gray" x-on:click="change(1)"
                      aria-label="Aumentar {{ $label }} em 15 minutos" />
        </x-slot:suffix>
        <x-slot:hint>
            <span x-text="reservationHint()"></span>
        </x-slot:hint>
    </x-input>
</div>
