<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Enums\EventStatusEnum;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

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
        $this->place = Place::query()->findOrFail($placeId);
        $this->placeId = $this->place->id;

        $this->reservations = $this->place->reservations()
            ->with('event:id,type,status')
            ->where('reserved_to', '>=', now())
            ->whereHas('event', fn ($query) => $query->whereNotIn('status', [
                EventStatusEnum::CANCELED->value,
                EventStatusEnum::REJECTED->value,
            ]))
            ->orderBy('reserved_from')
            ->limit(5)
            ->get()
            ->map(fn ($reservation): array => [
                'type' => $reservation->event->type->label(),
                'starts_at' => $reservation->reserved_from->format('d/m/Y H:i'),
                'ends_at' => $reservation->reserved_to->format('d/m/Y H:i'),
            ])
            ->all();

        $this->slide = true;
    }

    public function render(): View
    {
        return view('livewire.places.next-reservations');
    }
}
