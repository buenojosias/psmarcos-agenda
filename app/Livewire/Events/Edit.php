<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Edit extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);

        $this->event = $event;
    }

    public function render(): View
    {
        return view('livewire.events.edit');
    }
}
