<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Place;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Enums\EventStatusEnum;
use Livewire\Attributes\Locked;
use Illuminate\Contracts\View\View;

class NextReservations extends Component
{
    #[Locked]
    public ?int $placeId = null;

    public ?Place $place = null;

    public array $reservations = [];

    public bool $slide = false;

    #[On('show-place-reservations')]
    public function open(int $placeId): void
    {
        $this->place   = Place::query()->findOrFail($placeId);
        $this->placeId = $this->place->id;

        $this->reservations = $this->place->reservations()
            ->with(['event:id,type,status', 'mass:id,motivation,canceled_at'])
            ->where('reserved_to', '>=', now())
            ->where(function ($query): void {
                $query
                    ->whereHas('event', fn ($query) => $query->whereNotIn('status', [
                        EventStatusEnum::CANCELED->value,
                        EventStatusEnum::REFUSED->value,
                    ]))
                    ->orWhereHas('mass', fn ($query) => $query->whereNull('canceled_at'));
            })
            ->orderBy('reserved_from')
            ->limit(5)
            ->get()
            ->map(fn ($reservation): array => [
                'type'      => $reservation->event?->type->label() ?? ($reservation->mass?->motivation ?? 'Missa'),
                'starts_at' => $reservation->reserved_from->format('d/m/Y H:i'),
                'ends_at'   => $reservation->reserved_to->format('d/m/Y H:i'),
            ])
            ->all();

        $this->slide = true;
    }

    public function render(): View
    {
        return view('livewire.places.next-reservations');
    }
}
