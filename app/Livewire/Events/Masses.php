<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\EventTypeEnum;
use Illuminate\Contracts\View\View;

class Masses extends Listing
{
    public function render(): View
    {
        return view('livewire.events.masses', $this->listingData(
            $this->listingQuery()->where('type', EventTypeEnum::MASS),
        ));
    }
}
