<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Enums\EventStatusEnum;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Reservations extends Component
{
    public Place $place;

    public function mount(Place $place): void
    {
        $this->place = $place;
    }

    public function render(): View
    {
        return view('livewire.places.reservations', [
            'reservations' => $this->place->reservations()
                ->with(['event.group'])
                ->where('reserved_to', '>=', now())
                ->whereHas('event', fn ($query) => $query->whereNotIn('status', [
                    EventStatusEnum::CANCELED->value,
                    EventStatusEnum::REJECTED->value,
                ]))
                ->orderBy('reserved_from')
                ->get(),
        ]);
    }
}
