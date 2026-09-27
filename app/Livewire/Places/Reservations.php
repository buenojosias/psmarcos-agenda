<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Place;
use Livewire\Component;
use Carbon\CarbonImmutable;
use Livewire\WithPagination;
use App\Enums\EventStatusEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;

class Reservations extends Component
{
    use WithPagination;

    public Place $place;

    public mixed $date = null;

    public function mount(Place $place): void
    {
        $this->place = $place;
        $this->place->load('main');
    }

    public function updatedDate(): void
    {
        if (Validator::make(['date' => $this->date], ['date' => ['nullable', 'date_format:Y-m-d']])->fails()) {
            $this->date = null;
        }

        $this->resetPage();
    }

    public function render(): View
    {
        $date = is_string($this->date) && Validator::make(['date' => $this->date], ['date' => ['date_format:Y-m-d']])->passes()
            ? CarbonImmutable::parse($this->date)
            : null;

        return view('livewire.places.reservations', [
            'reservations' => $this->place->reservations()
                ->with(['event.group', 'mass'])
                ->where('reserved_to', '>=', now())
                ->when($date, fn ($query) => $query
                    ->where('reserved_from', '<', $date->addDay())
                    ->where('reserved_to', '>=', $date))
                ->where(function ($query): void {
                    $query
                        ->whereHas('event', fn ($query) => $query->whereNotIn('status', [
                            EventStatusEnum::CANCELED->value,
                            EventStatusEnum::REFUSED->value,
                        ]))
                        ->orWhereHas('mass', fn ($query) => $query->whereNull('canceled_at'));
                })
                ->orderBy('reserved_from')
                ->orderBy('id')
                ->paginate(15),
        ]);
    }
}
