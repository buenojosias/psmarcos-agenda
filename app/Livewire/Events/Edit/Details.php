<?php

declare(strict_types=1);

namespace App\Livewire\Events\Edit;

use App\Models\Event;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class Details extends Component
{
    public Event $event;

    public function render(): View
    {
        return view('livewire.events.edit.details');
    }
}
