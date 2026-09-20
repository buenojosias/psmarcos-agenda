<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Place;
use Livewire\Component;
use App\Enums\EventStatusEnum;
use Illuminate\Contracts\View\View;

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
                ->with(['event.group', 'mass'])
                ->where('reserved_to', '>=', now())
                ->where(function ($query): void {
                    $query
                        ->whereHas('event', fn ($query) => $query->whereNotIn('status', [
                            EventStatusEnum::CANCELED->value,
                            EventStatusEnum::REJECTED->value,
                        ]))
                        ->orWhereHas('mass', fn ($query) => $query->whereNull('canceled_at'));
                })
                ->orderBy('reserved_from')
                ->get(),
        ]);
    }
}
