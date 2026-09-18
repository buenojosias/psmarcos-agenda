<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Show extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    public function render(): View
    {
        Gate::authorize('view', $this->event);
        $this->event->load(['group', 'detail']);

        return view('livewire.events.show', [
            'canManage'    => Gate::allows('manage', $this->event),
            'canReview'    => Gate::allows('review', $this->event),
            'reservations' => $this->event->reservations()->with('place.community')->orderBy('reserved_from')->get(),
        ]);
    }
}
